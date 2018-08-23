<?php

namespace WpEcs\Service;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Process\Process;
use WpEcs\WordpressInstance;

class Migration
{
    /**
     * The source WordPress instance
     *
     * @var WordpressInstance
     */
    protected $source;

    /**
     * The destination WordPress instance
     *
     * @var WordpressInstance
     */
    protected $dest;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * Migration constructor.
     *
     * @param WordpressInstance $from
     * @param WordpressInstance $to
     */
    public function __construct(WordpressInstance $from, WordpressInstance $to, OutputInterface $output)
    {
        $this->source = $from;
        $this->dest   = $to;
        $this->output = $output;
    }

    public function migrate()
    {
        $this->beginStep('[1/4] Moving database...');
        $this->moveDatabase();
        $this->endStep();

        $this->beginStep('[2/4] Rewriting hostname...');
        $this->rewriteHostname();
        $this->endStep();

        $this->beginStep('[3/4] Rewriting S3 bucket URL...');
        $this->rewriteS3BucketUrl();
        $this->endStep();

        $this->beginStep('[4/4] Syncing S3 bucket...');
        $this->syncS3Bucket();
        $this->endStep();
    }

    protected function beginStep($name)
    {
        if ($this->output->getVerbosity() == OutputInterface::VERBOSITY_NORMAL) {
            $this->output->writeln($name);
        }
        else {
            $this->verboseSeparator();
            $this->output->writeln('<comment>' . strtoupper($name) . '</comment>');
            $this->verboseSeparator();
        }
    }

    protected function endStep()
    {
        $this->output->writeln('', OutputInterface::VERBOSITY_VERBOSE);
    }

    protected function moveDatabase()
    {
        $fh = tmpfile();
        $this->output->writeln('Exporting database from source...', OutputInterface::VERBOSITY_VERBOSE);
        $this->source->exportDatabase($fh);
        fseek($fh, 0);
        $this->output->writeln('Importing database to destination...', OutputInterface::VERBOSITY_VERBOSE);
        $this->dest->importDatabase($fh);
        fclose($fh);
    }

    protected function rewriteHostname()
    {
        $source = $this->source->env('SERVER_NAME');
        $destination   = $this->dest->env('SERVER_NAME');
        $this->dbSearchReplace($source, $destination);
    }

    protected function rewriteS3BucketUrl()
    {
        $source = $this->source->env('S3_UPLOADS_BASE_URL');
        $destination   = $this->dest->env('S3_UPLOADS_BASE_URL');
        $this->dbSearchReplace($source, $destination);
    }

    protected function dbSearchReplace($search, $replace)
    {
        $command = "wp --allow-root search-replace --report-changed-only \"$search\" \"$replace\"";
        $this->output->writeln("Running command on destination instance: <comment>$command</comment>\n", OutputInterface::VERBOSITY_VERBOSE);
        $result = $this->dest->execute($command);
        $this->output->write($result, false,OutputInterface::VERBOSITY_VERBOSE);
    }

    protected function syncS3Bucket()
    {
        $source = $this->source->Aws->s3BucketName;
        $dest = $this->dest->Aws->s3BucketName;

        $command = "aws s3 sync --delete s3://$source s3://$dest";
        $process = new Process($command);

        $this->output->writeln("Running command locally: <comment>$command</comment>\n", OutputInterface::VERBOSITY_VERBOSE);

        $streamOutput = function($type, $buffer) {
            $this->output->write($buffer, false, OutputInterface::VERBOSITY_VERBOSE);
        };
        $process->disableOutput();
        $process->mustRun($streamOutput);
    }

    protected function verboseSeparator()
    {
        $width = (new Terminal())->getWidth();
        $str = str_repeat('-', $width);
        $this->output->writeln($str, OutputInterface::VERBOSITY_VERBOSE);
    }
}
