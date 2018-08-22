<?php

namespace WpEcs;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use WpEcs\WordpressInstance\AwsResources;

/**
 * Class WordpressInstance
 */
class WordpressInstance
{
    /**
     * The 'appname' of this WordPress instance
     *
     * @var string
     */
    protected $appName;

    /**
     * The environment of this WordPress instance
     *
     * @var string
     */
    protected $env;

    /**
     * Holds the AWS resources associated with this WordPress instance
     * @var AwsResources
     */
    public $Aws;

    /**
     * WordpressInstance constructor
     *
     * @param string $appName The name of the site, as defined by the stack's `AppName` parameter
     * @param string $env The environment of the instance: dev|staging|prod
     */
    public function __construct($appName, $env)
    {
        $this->appName = $appName;
        $this->env = $env;
        $this->Aws = new AwsResources($appName, $env);
    }

    /**
     * Generate a `ssh` + `docker exec` command array suitable for using with Symfony's Process component.
     * Optionally, you can specify arguments to pass to both the `ssh` and `docker exec` commands.
     *
     * @param string $command Command to execute on the container
     * @param array $sshOptions Arguments to pass to the `ssh` command (optional)
     * @param array $dockerOptions Arguments to pass to the `docker exec` command (optional)
     * @return array
     */
    public function prepareCommand($command, $sshOptions = [], $dockerOptions = [])
    {
        $ssh = array_merge(
            [
                'ssh',
                "ec2-user@{$this->Aws->ec2Hostname}",
            ],
            $sshOptions
        );

        $docker = array_merge(
            ['docker exec'],
            $dockerOptions,
            [$this->Aws->dockerContainerId]
        );

        $ssh[] = implode(' ', $docker);
        $ssh[] = $command;

        return $ssh;
    }

    /**
     * Execute a command on the instance and return the output
     *
     * @param string $command
     * @return string
     */
    public function execute($command)
    {
        $process = new Process($this->prepareCommand($command));
        $process->mustRun();
        return $process->getOutput();
    }

    /**
     * Export the instance's database to the supplied file handle
     *
     * @param resource $fh An open file handle to export to
     * @throws ProcessFailedException if the process didn't terminate successfully
     */
    public function exportDatabase($fh)
    {
        $command = 'wp --allow-root db export -';
        $process = new Process($this->prepareCommand($command));

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
        $saveOutputStream = function($type, $buffer) use ($fh) {
            if ($type === Process::OUT) {
                fwrite($fh, $buffer);
            }
        };
        $process->disableOutput();
        $process->mustRun($saveOutputStream);
    }

    /**
     * Import the supplied file handle into the instance's database
     *
     * @param resource $fh An open file handle to import from
     * @throws ProcessFailedException if the process didn't terminate successfully
     */
    public function importDatabase($fh)
    {
        $command = $this->prepareCommand(
            'wp --allow-root db import -',
            [],
            ['-i']
        );
        $process = new Process($command);
        $process->setInput($fh);
        $process->mustRun();
    }

    /**
     * Get the value of an environment variable in the container
     *
     * @param string $var Name of the environment variable
     * @return string Value of the environment variable
     */
    public function env($var)
    {
        $value = $this->execute("printenv $var");
        return trim($value);
    }
}
