<?php

namespace WpEcs\Service;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Process\Process;
use WpEcs\Wordpress\AbstractInstance;
use WpEcs\Wordpress\LocalInstance;

class Migration
{
    /**
     * The destination WordPress instance
     *
     * @var AbstractInstance
     */
    protected $dest;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * The source WordPress instance
     *
     * @var AbstractInstance
     */
    protected $source;

    /**
     * Migration constructor.
     *
     * @param AbstractInstance $source
     * @param AbstractInstance $destination
     * @param OutputInterface $output
     */
    public function __construct(AbstractInstance $source, AbstractInstance $destination, OutputInterface $output)
    {
        $this->source = $source;
        $this->dest   = $destination;
        $this->output = $output;
    }

    /**
     * Perform the migration
     */
    public function migrate()
    {
        if ($this->actionNotAllowed()) {
            $this->output->writeln('Operation cancelled: <comment>$this->dest</comment> is not available for migration. Try using: "<appname>:dev"');
            return false;
        }

        $this->beginStep('[1/3] Moving database...');
        $this->moveDatabase();
        $this->endStep();

        $this->beginStep('[2/3] Rewriting database...');
        $this->rewriteDatabase();
        $this->endStep();

        $this->beginStep('[3/3] Syncing media uploads...');
        $this->syncUploads();
        $this->endStep();
    }

    /**
     * Helper method to output the current step name
     * Formatting will depend on output verbosity level
     *
     * @param string $name
     */
    public function beginStep($name)
    {
        if ($this->output->getVerbosity() == OutputInterface::VERBOSITY_NORMAL) {
            $this->output->writeln($name);
            return;
        }

        $terminalWidth = (new Terminal())->getWidth();
        $separator     = str_repeat('-', $terminalWidth);

        $this->output->writeln($separator, OutputInterface::VERBOSITY_VERBOSE);
        $this->output->writeln('<comment>' . strtoupper($name) . '</comment>', OutputInterface::VERBOSITY_VERBOSE);
        $this->output->writeln($separator, OutputInterface::VERBOSITY_VERBOSE);
    }

    /**
     * Move the database from `source` to `destination`
     *
     * - Export `source` database to a local temporary file
     * - Import that temporary file into `destination`
     * - Delete the temporary file
     */
    public function moveDatabase()
    {
        $file = tmpfile();
        $this->output->writeln('Exporting database from source...', OutputInterface::VERBOSITY_VERBOSE);
        $this->source->exportDatabase($file);
        rewind($file);
        $this->output->writeln('Importing database into destination...', OutputInterface::VERBOSITY_VERBOSE);
        $this->dest->importDatabase($file);
        fclose($file);
    }

    /**
     * Helper method to output the end of a step
     * This outputs a blank line to visually separate between steps
     */
    public function endStep()
    {
        $this->output->writeln('', OutputInterface::VERBOSITY_VERBOSE);
    }

    /**
     * Perform search & replace operations on the `destination` database
     * This step is necessary to rewrite the hostname and other content which references the `source` instance
     *
     * - Rewrite media upload URLs
     * - Rewrite website URLs
     * - Rewrite references to site domain name
     */
    public function rewriteDatabase()
    {
        // Rewrite media upload URLs
        $this->output->writeln('Rewriting references to media uploads base URL...', OutputInterface::VERBOSITY_VERBOSE);
        $this->dbSearchReplace(
            $this->source->uploadsBaseUrl,
            $this->dest->uploadsBaseUrl
        );

        // Rewrite 'http://example.com' to 'http://newdomain.com'
        // Since this contains the protocol ('http:') it will migrate between http/https
        $this->output->writeln('Rewriting references to site URL...', OutputInterface::VERBOSITY_VERBOSE);
        $this->rewriteEnvVar('WP_HOME');

        // Rewrite 'example.com' to 'newdomain.com'
        // This rewrites any other references to the domain name which didn't match WP_HOME above
        $this->output->writeln('Rewriting references to server name...', OutputInterface::VERBOSITY_VERBOSE);
        $this->rewriteEnvVar('SERVER_NAME');
    }

    /**
     * Perform a search & replace operation on the `destination` database
     *
     * @param string $search
     * @param string $replace
     */
    public function dbSearchReplace($search, $replace)
    {
        $command = [
            'wp',
            '--allow-root',
            'search-replace',
            '--report-changed-only',
            $search,
            $replace,
        ];

        $this->output->writeln(
            "Search for <comment>\"$search\"</comment> & replace with <comment>\"$replace\"</comment>",
            OutputInterface::VERBOSITY_VERBOSE
        );
        $result = $this->dest->execute($command);
        $this->output->writeln($result, OutputInterface::VERBOSITY_VERBOSE);
    }

    /**
     * Search & replace the DB for an environment variable
     * Given the name of an environment variable, replace the value from `source` with the value in `destination`
     *
     * e.g. $this->rewriteEnvVar('SERVER_NAME')
     *      might perform a database search & replace for
     *      'example.com' => 'newhostname.com'
     *
     * @param string $var
     */
    protected function rewriteEnvVar($var)
    {
        $this->dbSearchReplace(
            $this->source->env($var),
            $this->dest->env($var)
        );
    }

    /**
     * Sync media upload files from `source` to `destination`
     * If the migration is to sync two local instances, it'll use `rsync`
     * Otherwise `aws s3 sync` will be used to sync to/from an S3 bucket
     */
    public function syncUploads()
    {
        $sourcePath = $this->source->uploadsPath;
        $destPath   = $this->dest->uploadsPath;
        $this->output->writeln(
            "Syncing files from <comment>$sourcePath</comment> to <comment>$destPath</comment>",
            OutputInterface::VERBOSITY_VERBOSE
        );

        $command = ($this->source instanceof LocalInstance && $this->dest instanceof LocalInstance) ?
            // Sync files using `rsync` if both instances are local
            "rsync -avh --delete \"$sourcePath/\" \"$destPath/\"" :
            // Otherwise sync files using `aws s3 sync`
            "aws s3 sync --delete \"$sourcePath\" \"$destPath\"";

        $streamOutput = function ($type, $buffer) {
            $this->output->write($buffer, false, OutputInterface::VERBOSITY_VERBOSE);
            unset($type); // To suppress phpmd unused local variable warning
        };

        $process = $this->newProcess($command);
        $this->output->writeln("Running command: <comment>$command</comment>", OutputInterface::VERBOSITY_VERBOSE);
        $process->disableOutput();
        $process->mustRun($streamOutput);
    }

    public function newProcess($command)
    {
        $process = new Process($command);
        $process->setTimeout(null);
        return $process;
    }

    private function actionNotAllowed()
    {
        return in_array(strstr($this->dest, ':'), [':staging', ':prod']);
    }
}
