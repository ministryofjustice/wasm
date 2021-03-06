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
            $value                = $this->execute("printenv $var");
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
     * @throws ProcessFailedException if the process didn't exit successfully
     */
    public function exportDatabase($file, $errorOut = STDERR)
    {
        $process = $this->newCommand('wp --allow-root db export -');

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

        $process->setTimeout(300);
        $process->disableOutput();

        $process->mustRun($saveOutputStream);
    }

    /**
     * Import the supplied file handle into the instance's database
     *
     * @param resource $file An open file handle to import from
     *
     * @throws ProcessFailedException if the process didn't exit successfully
     */
    public function importDatabase($file)
    {
        $process = $this->newCommand('wp --allow-root db import -', ['-i']);
        $process->setInput($file);
        $process->setTimeout(300);
        $process->mustRun();
    }
}
