<?php

namespace WpEcs;

use Symfony\Component\Process\Process;
use WpEcs\WordpressInstance\AwsResources;

/**
 * Class WordpressInstance
 */
class WordpressInstance
{
    protected $appName;

    protected $env;

    public $Aws;

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

    public function execute($command)
    {
        $process = new Process($this->prepareCommand($command));
        $process->mustRun();
        return $process->getOutput();
    }
}
