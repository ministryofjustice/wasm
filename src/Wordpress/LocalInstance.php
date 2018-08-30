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
     * @param string $workingDirectory
     */
    public function __construct($workingDirectory = null)
    {
        if (is_null($workingDirectory)) {
            $workingDirectory = getcwd();
        }

        $this->workingDirectory = $workingDirectory;
    }

    /**
     * Get the ID of the running docker container for this WordPress instance
     *
     * @return string
     */
    protected function getDockerContainerId()
    {
        $process = new Process('docker-compose ps -q wordpress');
        $process->setWorkingDirectory($this->workingDirectory);
        $id = $process->mustRun()->getOutput();

        return trim($id);
    }

    protected function getUploadsBaseUrl()
    {
        $homeUrl = $this->env('WP_HOME');
        return $homeUrl . '/app/uploads';
    }

    protected function getUploadsPath()
    {
        $directory = 'web/app/uploads';
        $path = $this->workingDirectory . DIRECTORY_SEPARATOR . $directory;
        return realpath($path);
    }

    public function newCommand($command, $dockerOptions = [], ...$options)
    {
        $command = $this->prepareCommand($command, $dockerOptions);
        $process = new Process($command);
        $process->setWorkingDirectory($this->workingDirectory);

        return $process;
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
