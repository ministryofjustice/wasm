<?php

namespace WpEcs\Wordpress;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

abstract class AbstractInstance
{
    /**
     * Get the value of an environment variable in the container
     *
     * @param string $var Name of the environment variable
     *
     * @return string Value of the environment variable
     */
    public function env($var)
    {
        $value = $this->execute("printenv $var");

        return trim($value);
    }

    /**
     * Execute a command on the instance and return the output
     *
     * @param string $command
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
     * @param resource $fh An open file handle to export to
     *
     * @throws ProcessFailedException if the process didn't exit successfully
     */
    public function exportDatabase($fh)
    {
        $process = $this->newCommand('wp --allow-root db export -');

        /**
         * Callback to process Process output
         *
         * Output is saved to the open file handle ($fh) in chunks.
         * Also notice that Process output capturing is disabled with $process->disableOutput();
         *
         * Together, this avoids the need to load the entire DB dump into an in-memory variable before writing it out to disk.
         * Instead, the dump is written to disk as it arrives, and is never stored in memory.
         *
         * @param string $type
         * @param string $buffer
         */
        $saveOutputStream = function ($type, $buffer) use ($fh) {
            if ($type === Process::OUT) {
                fwrite($fh, $buffer);
            } else {
                fwrite(STDERR, $buffer);
            }
        };

        $process->disableOutput();
        $process->mustRun($saveOutputStream);
    }

    /**
     * Import the supplied file handle into the instance's database
     *
     * @param resource $fh An open file handle to import from
     *
     * @throws ProcessFailedException if the process didn't exit successfully
     */
    public function importDatabase($fh)
    {
        $process = $this->newCommand('wp --allow-root db import -', ['-i']);
        $process->setInput($fh);
        $process->mustRun();
    }
}
