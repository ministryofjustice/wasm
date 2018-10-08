<?php

namespace WpEcs\Wordpress\AwsInstance;

use Aws\CloudFormation\Exception\CloudFormationException;
use Aws\Sdk;
use Symfony\Component\Process\Process;
use WpEcs\Traits\LazyPropertiesTrait;

/**
 * Class AwsResources
 *
 * This class represents the AWS Resources associated with a WordPress instance running in AWS
 *
 * @property-read string stackName
 * @property-read string ecsCluster
 * @property-read string ecsServiceName
 * @property-read string ecsTaskArn
 * @property-read string ec2Hostname
 * @property-read string dockerContainerId
 * @property-read string s3BucketName
 * @property-read bool   stackIsActive
 */
class AwsResources
{
    use LazyPropertiesTrait;

    protected $appName;

    protected $env;

    protected $sdk;

    public function __construct($appName, $env, Sdk $sdk)
    {
        $this->appName = $appName;
        $this->env     = $env;
        $this->sdk     = $sdk;
    }

    public function newProcess($command)
    {
        return new Process($command);
    }

    protected function getDockerContainerId()
    {
        $hostTask = ($this->newProcess([
            'ssh',
            "ec2-user@{$this->ec2Hostname}",
            "curl -s localhost:51678/v1/tasks?taskarn={$this->ecsTaskArn}",
        ]))->mustRun()->getOutput();

        $hostTask = json_decode($hostTask, true);

        if (empty($hostTask['Containers'])) {
            throw new \Exception('There are no containers running on the host for this ECS Task');
        }

        foreach ($hostTask['Containers'] as $container) {
            if ($container['Name'] == 'web') {
                return $container['DockerId'];
            }
        }

        throw new \Exception("There is no 'web' container running on the host for this ECS Task");
    }

    protected function getEc2Hostname()
    {
        $ecs = $this->sdk->createEcs();
        $ec2 = $this->sdk->createEc2();

        $containerInstance = $ecs->describeTasks([
            'cluster' => $this->ecsCluster,
            'tasks'   => [$this->ecsTaskArn],
        ])['tasks'][0]['containerInstanceArn'];

        $ec2Instance = $ecs->describeContainerInstances([
            'cluster'            => $this->ecsCluster,
            'containerInstances' => [$containerInstance],
        ])['containerInstances'][0]['ec2InstanceId'];

        $ec2Hostname = $ec2->describeInstances([
            'InstanceIds' => [$ec2Instance]
        ])['Reservations'][0]['Instances'][0]['PublicDnsName'];

        return $ec2Hostname;
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
            'StackName'         => $this->stackName,
            'LogicalResourceId' => 'WebService',
        ]);

        $serviceArn = $resource['StackResourceDetail']['PhysicalResourceId'];
        preg_match('/service\/(.*)/', $serviceArn, $matches);

        return $matches[1];
    }

    protected function getEcsTaskArn()
    {
        $ecs     = $this->sdk->createEcs();
        $taskArn = $ecs->listTasks([
            'cluster'     => $this->ecsCluster,
            'serviceName' => $this->ecsServiceName
        ])['taskArns'][0];

        return $taskArn;
    }

    protected function getS3BucketName()
    {
        $cloudformation = $this->sdk->createCloudFormation();

        $resource = $cloudformation->describeStackResource([
            'StackName'         => $this->stackName,
            'LogicalResourceId' => 'Storage',
        ]);

        return $resource['StackResourceDetail']['PhysicalResourceId'];
    }

    protected function getStackName()
    {
        return "{$this->appName}-{$this->env}";
    }
}
