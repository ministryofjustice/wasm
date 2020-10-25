<?php

namespace WpEcs\Wordpress;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use WpEcs\Traits\LazyPropertiesTrait;

/**
 * Class AbstractInstance
 *
 * @property-read string $uploadsBaseUrl
 * @property-read string $uploadsPath
 */
abstract class AbstractInstance
{
    use LazyPropertiesTrait;

    /**
     * Holds a cache of env variables
     *
     * @var array
     */
    protected $envCache = [];

    /**
     * A filename-friendly way to identify the instance
     * Does not need to be a valid Instance Identifier
     * e.g. 'mysite-dev' is fine
     *
     * @var string
     */
    public $name;

    /**
     * Flag to indicate a multisite network instance
     *
     * @var boolean
     */
    public $multisite;

    /**
     * Multisite
     * States the --url flags for the source and destination instances
     *
     * @var string
     */
    public $url;

    /**
     * @var mixed|string
     */
    public $tables;

    /**
     * @var mixed
     */
    public $blog_id;
    /**
     * @var mixed
     */
    private $url_is_domain;

    /**
     * Get the value of an environment variable in the container
     * Values are cached for the life of the object to avoid repeat calls to the container for env variables
     *
     * @param string $var Name of the environment variable
     *
     * @return string Value of the environment variable
     */
    public function env($var)
    {
        if (!isset($this->envCache[$var])) {
            $value = $this->execute("printenv $var");
            $this->envCache[$var] = trim($value);
        }

        return $this->envCache[$var];
    }

    /**
     * Execute a command on the instance and return the output
     *
     * @param string|array $command
     *
     * @return string
     * @throws ProcessFailedException if the process didn't exit successfully
     */
    public function execute($command)
    {
        $process = $this->newCommand($command);

        return $process->mustRun()->getOutput();
    }

    /**
     * Return a new Process instance which, when run, will execute a command on the instance
     *
     * @param string|array $command The command to run
     * @param array $dockerOptions Options for the `docker exec` command (optional)
     * @param mixed ...$options
     *
     * @return Process
     */
    abstract public function newCommand($command, $dockerOptions = [], ...$options);

    /**
     * Export the instance's database to the supplied file handle
     *
     * @param resource $file An open file handle to export to
     * @param resource|bool $errorOut Error output will be written here
     *
     */
    public function exportDatabase($file, $errorOut = STDERR)
    {
        $this->detectNetwork();

        $command = 'wp --allow-root db export -';

        if ($this->multisite) {
            $command .= ' ' . (!empty($this->url)
                ? $this->tablesFilter()
                : $this->urlFlag()
            );
        }

        $process = $this->newCommand($command);

        /**
         * Callback to process Process output
         *
         * Output is saved to the open file handle ($file) in chunks.
         * Also notice that Process output capturing is disabled with $process->disableOutput();
         *
         * This avoids the need to load the entire DB dump into an in-memory variable before writing it out to disk.
         * Instead, the dump is written to disk as it arrives, and is never stored in memory.
         *
         * @param string $type
         * @param string $buffer
         */
        $saveOutputStream = function ($type, $buffer) use ($file, $errorOut) {
            if ($type === Process::ERR) {
                fwrite($errorOut, $buffer);
                return;
            }

            fwrite($file, $buffer);
        };

        $process->setTimeout(600); // 10 minutes for large db's
        $process->disableOutput();

        $process->mustRun($saveOutputStream);
    }

    /**
     * Import the supplied file handle into the instance's database
     *
     * @param resource $file An open file handle to import from
     */
    public function importDatabase($file)
    {
        $this->detectNetwork();

        $command = 'wp --allow-root db import -';

        if ($this->multisite) {
            $command .= ' ' . $this->urlFlag();
        }

        $process = $this->newCommand($command, ['-i']);
        $process->setInput($file);
        $process->setTimeout(900); // 15 minutes for large db's
        $process->mustRun();
    }

    /**
     * Checks if we are interacting with a Multisite network.
     * Uses exit codes:
     * - Exit code 0 = multisite installed
     * - Anything other than a 0 throws an exception (this includes standard WP installations)
     *
     * https://developer.wordpress.org/cli/commands/core/is-installed/
     */
    public function detectNetwork()
    {
        try {
            $this->execute('wp --allow-root core is-installed --network');
            $this->multisite = true;
        } catch (ProcessFailedException $exception) {
            $this->multisite = false;
        }
    }

    /**
     * Multisite --url flag filter.
     * Formats the url portion of a source or destination argument
     * Supports custom domain names
     *
     * Sub-site identifiers only contain alphanumeric and dash characters. Custom domains all have dots (.) in them.
     * We use this understanding to filter the resulting url for the multisite --url flag in a command
     *
     * Logic:
     * 2. check if we have a domain name
     * 3. if so, return the domain, otherwise
     * 4. check if there was any value passed through $this->url
     * 5. if so, append this value to the SERVER_NAME var, otherwise
     * 6. return the SERVER_NAME var (targets entire DB)
     *
     * @return string
     */
    public function urlFlag(): string
    {
        return '--url=' . ($this->urlIsDomain()
                ? $this->url
                : (!empty($this->url)
                    ? $this->env('SERVER_NAME') . '/' . $this->url
                    : $this->env('SERVER_NAME')
                )
            );
    }

    /**
     * Multisite
     * Collects a list of sub-site tables
     * Uses urlFlag() to target a specific site
     * @param bool $prefix
     * @param string $format
     * @return string
     */
    public function tablesFilter($prefix = true, $format = 'csv'): string
    {
        if (!$this->tables) {
            $command = [
                'wp',
                'db',
                'tables',
                '--scope=blog',
                $this->urlFlag(),
                '--allow-root',
                '--format=' . $format,
            ];

            $this->tables = rtrim($this->execute($command));
        }

        return ($prefix ? '--tables=' : '') . $this->tables;
    }

    /**
     * Tests the url property for any remaining characters, after stripping WP allowed sub-site characters.
     * When preg_match is true, we can be certain we don't have a standard sub-site name, we have a custom domain.
     *
     * Does not support localhost or host names without periods (.)
     *
     * WP site naming convention, characters are limited to:
     * - alphanumerics
     * - dashes
     *
     * @return bool
     */
    protected function urlIsDomain()
    {
        if ($this->url_is_domain === null) {
            $this->url_is_domain = preg_match('/[^A-Za-z0-9-]/', $this->url) ? true : false;
        }

        return $this->url_is_domain;
    }

    /**
     * @return mixed
     */
    public function getBlogId()
    {
        if ($this->blog_id) {
            return $this->blog_id;
        }

        $sites = json_decode($this->execute('wp --allow-root site list --fields=blog_id,url --format=json'));
        if (is_array($sites)) {
            foreach ($sites as $site) {
                $testcase = ($this->urlIsDomain() ? $this->url : '/' . $this->url . '/');
                if (strpos($site->url, $testcase) > -1) {
                    $this->blog_id = $site->blog_id;
                    return $site->blog_id;
                }
            }
        }
    }

    public function createSite($sourceId)
    {
        $domain = $this->env('SERVER_NAME');
        $path = '/' . $this->url . '/';
        $date = date('Y-m-d H:i:s');

        if ($this->urlIsDomain()) {
            $domain = $this->url;
            $path = '/';
        }

        $columns = '`blog_id`, `site_id`, `domain`, `path`, `registered`, `last_updated`';
        $values = "$sourceId, 1, '$domain', '$path', '$date', '$date'";

        $newSite = 'wp --allow-root db query "INSERT INTO wp_blogs (' . $columns . ') VALUES (' . $values . ')"';

        $process = $this->newCommand($newSite);
        $process->mustRun();
    }

    public function addSiteMeta($sourceId)
    {
        $siteCmdVersion = 'wp --allow-root site meta add ' . $sourceId . ' db_version ' . $this->getDBVersion();
        $siteCmdUpdated = 'wp --allow-root site meta add ' . $sourceId . ' db_last_updated "' . microtime() . '"';

        $process = $this->newCommand($siteCmdVersion);
        $process->mustRun();

        $process = $this->newCommand($siteCmdUpdated);
        $process->mustRun();
    }

    protected function getDBVersion()
    {
        $versions = explode("\n", $this->execute('wp --allow-root core version --extra'));
        foreach ($versions as $version) {
            if (strpos($version, 'Database') > -1) {
                return trim(strstr($version, ':'), ': ');
            }
        }
    }
}
