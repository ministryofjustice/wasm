<?php

namespace WpEcs\Wordpress;

use Symfony\Component\Process\Process;
use WpEcs\Wordpress\AwsInstance\AwsResources;

class AwsInstance extends AbstractInstance
{
    /**
     * Holds the AWS resources associated with this WordPress instance
     *
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
        $this->Aws  = new AwsResources($appName, $env);
        $this->name = "$appName-$env";
    }

    protected function getUploadsBaseUrl()
    {
        return $this->env('S3_UPLOADS_BASE_URL');
    }

    protected function getUploadsPath()
    {
        return "s3://{$this->Aws->s3BucketName}/uploads";
    }

    public function newCommand($command, $dockerOptions = [], ...$options)
    {
        if (count($options) > 0) {
            $sshOptions = $options[0];
        } else {
            $sshOptions = [];
        }

        $command = $this->prepareCommand($command, $dockerOptions, $sshOptions);
        $process = new Process($command);

        return $process;
    }

    /**
     * Generate a `ssh` + `docker exec` command array suitable for using with Symfony's Process component.
     * Optionally, you can specify arguments to pass to both the `ssh` and `docker exec` commands.
     *
     * @param string $command Command to execute on the container
     * @param array $dockerOptions Arguments to pass to the `docker exec` command (optional)
     * @param array $sshOptions Arguments to pass to the `ssh` command (optional)
     *
     * @return array
     */
    protected function prepareCommand($command, $dockerOptions = [], $sshOptions = [])
    {
        // Split command string into array of arguments
        if (is_string($command)) {
            $command = str_getcsv($command, ' ');
        }

        // Wrap all command arguments in single quotes so they pass-through to docker container unharmed (e.g. spaces intact)
        $command = array_map(function($item) {
            return "'$item'";
        }, $command);

        return array_merge(
            [
                'ssh',
                "ec2-user@{$this->Aws->ec2Hostname}",
            ],
            $sshOptions,
            [
                'docker',
                'exec',
            ],
            $dockerOptions,
            [
                $this->Aws->dockerContainerId,
            ],
            $command
        );
    }
}