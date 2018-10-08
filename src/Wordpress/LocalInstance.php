<?php

namespace WpEcs\Wordpress;

use Symfony\Component\Process\Process;
use WpEcs\Traits\LazyPropertiesTrait;

/**
 * Class LocalInstance
 *
 * @property-read string $dockerContainerId
 */
class LocalInstance extends AbstractInstance
{
    use LazyPropertiesTrait;

    /**
     * The path to a local WordPress instance
     *
     * @var string
     */
    public $workingDirectory;

    /**
     * LocalInstance constructor.
     *
     * @param string $workingDirectory Path to the instance directory (can be relative)
     */
    public function __construct($workingDirectory)
    {
        $this->workingDirectory = $workingDirectory;
        $this->name             = basename($workingDirectory);
    }

    /**
     * Get the ID of the running docker container for this WordPress instance
     *
     * @return string
     */
    protected function getDockerContainerId()
    {
        $process = $this->newProcess('docker-compose ps -q wordpress');
        $dockerId = $process->mustRun()->getOutput();

        return trim($dockerId);
    }

    protected function getUploadsBaseUrl()
    {
        $homeUrl = $this->env('WP_HOME');

        return $homeUrl . '/app/uploads';
    }

    protected function getUploadsPath()
    {
        return $this->workingDirectory . '/web/app/uploads';
    }

    protected function newProcess($command)
    {
        $process = new Process($command);
        $process->setWorkingDirectory($this->workingDirectory);

        return $process;
    }

    /**
     * Return a new Process instance which, when run, will execute a command on the instance
     *
     * @param string|array $command The command to run
     * @param array $dockerOptions Options for the `docker exec` command (optional)
     * @param mixed ...$options
     *
     * @return Process
     * @SuppressWarnings(PHPMD.UnusedFormalParameter) because we don't use $options, but the parent method defines it
     */
    public function newCommand($command, $dockerOptions = [], ...$options)
    {
        $command = $this->prepareCommand($command, $dockerOptions);

        return $this->newProcess($command);
    }

    /**
     * Generate a `docker exec` command array suitable for using with Symfony's Process component.
     * Optionally, specify arguments to pass to the `docker exec` command.
     *
     * @param string $command Command to execute on the container
     * @param array $dockerOptions Arguments to pass to the `docker exec` command (optional)
     *
     * @return array
     */
    protected function prepareCommand($command, $dockerOptions = [])
    {
        if (is_string($command)) {
            $command = str_getcsv($command, ' ');
        }

        return array_merge(
            [
                'docker',
                'exec',
            ],
            $dockerOptions,
            [
                $this->dockerContainerId,
            ],
            $command
        );
    }
}
