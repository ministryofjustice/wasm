<?php

namespace WpEcs;

use Aws\Sdk;
use Symfony\Component\Process\Process;
use WpEcs\Traits\LazyProperties;

/**
 * Class WordpressInstance
 *
 * @property-read string ecsCluster
 * @property-read string ecsServiceName
 * @property-read string ecsTaskArn
 * @property-read string ec2Hostname
 * @property-read string dockerContainerId
 */
class WordpressInstance
{
    use LazyProperties;

    protected $appName;

    protected $env;

    protected $stackName;

    protected $sdk;

    public function __construct($appName, $env)
    {
        $this->appName   = $appName;
        $this->env       = $env;
        $this->stackName = "{$appName}-{$env}";

        $this->sdk = new Sdk([
            'region' => 'eu-west-2',
            'version' => 'latest',
        ]);
    }

    protected function getEcsCluster()
    {
        switch ($this->env) {
            case 'dev':
                return 'wp-dev';
            case 'staging':
                return 'wp-staging';
            case 'prod':
                return 'wp-production';
            default:
                throw new \Exception('Bad environment specified');
        }
    }

    protected function getEcsServiceName()
    {
        $cloudformation = $this->sdk->createCloudFormation();

        $resource = $cloudformation->describeStackResource([
            'StackName' => $this->stackName,
            'LogicalResourceId' => 'WebService',
        ]);

        $serviceArn = $resource['StackResourceDetail']['PhysicalResourceId'];
        preg_match('/service\/(.*)/', $serviceArn, $matches);
        return $matches[1];
    }

    protected function getEcsTaskArn()
    {
        $ecs = $this->sdk->createEcs();
        $taskArn = $ecs->listTasks([
            'cluster' => $this->ecsCluster,
            'serviceName' => $this->ecsServiceName
        ])['taskArns'][0];
        return $taskArn;
    }

    protected function getEc2Hostname()
    {
        $ecs = $this->sdk->createEcs();
        $ec2 = $this->sdk->createEc2();

        $containerInstance = $ecs->describeTasks([
            'cluster' => $this->ecsCluster,
            'tasks' => [$this->ecsTaskArn],
        ])['tasks'][0]['containerInstanceArn'];

        $ec2Instance = $ecs->describeContainerInstances([
            'cluster' => $this->ecsCluster,
            'containerInstances' => [$containerInstance],
        ])['containerInstances'][0]['ec2InstanceId'];

        $ec2Hostname = $ec2->describeInstances([
            'InstanceIds' => [$ec2Instance]
        ])['Reservations'][0]['Instances'][0]['PublicDnsName'];

        return $ec2Hostname;
    }

    public function getDockerContainerId()
    {
        $hostTask = (new Process([
            'ssh',
            "ec2-user@{$this->ec2Hostname}",
            "curl -s localhost:51678/v1/tasks?taskarn={$this->ecsTaskArn}",
        ]))->mustRun()->getOutput();

        $hostTask = json_decode($hostTask, true);

        foreach ($hostTask['Containers'] as $container) {
            if ($container['Name'] == 'web') {
                return $container['DockerId'];
            }
        }

        throw new \Exception('Docker container not found on host');
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
                "ec2-user@{$this->ec2Hostname}",
            ],
            $sshOptions
        );

        $docker = array_merge(
            ['docker exec'],
            $dockerOptions,
            [$this->dockerContainerId]
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
