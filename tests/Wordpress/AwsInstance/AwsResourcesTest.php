<?php

namespace WpEcs\Tests\Wordpress\AwsInstance;

use Aws\CloudFormation\Exception\CloudFormationException;
use Aws\CommandInterface;
use PHPUnit\Framework\TestCase;
use WpEcs\Wordpress\AwsInstance\AwsResources;
use Aws\Sdk;
use Aws\Ecs\EcsClient;
use Aws\Ec2\Ec2Client;
use Aws\CloudFormation\CloudFormationClient;
use Aws\Result;
use Symfony\Component\Process\Process;
use Exception;

class AwsResourcesTest extends TestCase
{
    /**
     * @var AwsResources
     */
    protected $subject;

    /**
     * @var Sdk
     */
    protected $sdk;

    protected function setUp()
    {
        $this->sdk = $this->mockSdk();

        $this->subject = new AwsResources(
            'example',
            'dev',
            $this->sdk
        );
    }

    public function testStackName()
    {
        $this->assertEquals(
            'example-dev',
            $this->subject->stackName
        );
    }

    public function ecsClusterProvider()
    {
        return [
            [
                'example',
                'dev',
                'wp-dev',
            ],
            [
                'example',
                'staging',
                'wp-staging',
            ],
            [
                'example',
                'prod',
                'wp-production',
            ],
        ];
    }

    /**
     * @dataProvider ecsClusterProvider
     *
     * @param string $appName
     * @param string $env
     * @param string $cluster
     */
    public function testEcsCluster($appName, $env, $cluster)
    {
        $subject = new AwsResources($appName, $env, $this->mockSdk());
        $this->assertEquals($cluster, $subject->ecsCluster);
    }

    public function testBadEcsCluster()
    {
        $subject = new AwsResources('example', 'bad-env-name', $this->mockSdk());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Bad environment specified');

        $subject->ecsCluster;
    }

    public function testS3BucketName()
    {
        $this->assertEquals(
            'example-dev-bucket',
            $this->subject->s3BucketName
        );
    }

    public function testEcsServiceName()
    {
        $expected = 'example-dev-WebService-XXXXXXXXXXXXX';
        $actual = $this->subject->ecsServiceName;
        $this->assertEquals($expected, $actual);
    }

    public function testEcsTaskArn()
    {
        $this->assertEquals(
            'arn:aws:ecs:eu-west-2:000000000000:task/4a0223a8-a99a-45dc-981b-1fccc27a8cc2',
            $this->subject->ecsTaskArn
        );
    }

    public function testEc2Hostname()
    {
        $this->assertEquals(
            'ec2-61-18-95-225.eu-west-2.compute.amazonaws.com',
            $this->subject->ec2Hostname
        );
    }

    public function dockerContainerIdProvider()
    {
        return [
            [
                // Happy path data
                '4c6ebc55289db174d6c40af51eabe3c37deb2a28e7a08b400dc0644b383ae0bb',
                false,
                '{"Arn":"arn:aws:ecs:eu-west-2:000000000000:task/4a0223a8-a99a-45dc-981b-1fccc27a8cc2","DesiredStatus":"RUNNING","KnownStatus":"RUNNING","Family":"example-dev","Version":"27","Containers":[{"DockerId":"4c6ebc55289db174d6c40af51eabe3c37deb2a28e7a08b400dc0644b383ae0bb","DockerName":"ecs-example-dev-27-web-8246fc7457ced635711e","Name":"web","Ports":[{"ContainerPort":80,"Protocol":"tcp","HostPort":32893}]}]}',
            ],
            [
                // Docker container does not exist on host. Expect an exception.
                false,
                "There is no 'web' container running on the host for this ECS Task",
                '{"Arn":"arn:aws:ecs:eu-west-2:000000000000:task/4a0223a8-a99a-45dc-981b-1fccc27a8cc2","DesiredStatus":"RUNNING","KnownStatus":"RUNNING","Family":"example-dev","Version":"27","Containers":[{"DockerId":"4c6ebc55289db174d6c40af51eabe3c37deb2a28e7a08b400dc0644b383ae0bb","DockerName":"ecs-example-dev-27-web-8246fc7457ced635711e","Name":"something-other-than-web","Ports":[{"ContainerPort":80,"Protocol":"tcp","HostPort":32893}]}]}',
            ],
            [
                // Docker container does not exist on host. Expect an exception.
                false,
                'There are no containers running on the host for this ECS Task',
                '{"Arn":"","KnownStatus":"","Family":"","Version":"","Containers":null}',
            ]
        ];
    }

    /**
     * @dataProvider dockerContainerIdProvider
     * @param string|bool $expected
     * @param string|bool $expectedExceptionMessage
     * @param string $jsonOutput
     */
    public function testDockerContainerId($expected, $expectedExceptionMessage, $jsonOutput)
    {
        $expectedSshCommand = [
            'ssh',
            'ec2-user@ec2-61-18-95-225.eu-west-2.compute.amazonaws.com',
            'curl -s localhost:51678/v1/tasks?taskarn=arn:aws:ecs:eu-west-2:000000000000:task/4a0223a8-a99a-45dc-981b-1fccc27a8cc2'
        ];

        $process = $this->createMock(Process::class);
        $process->expects($this->once())
                ->method('mustRun')
                ->willReturnSelf();
        $process->expects($this->once())
                ->method('getOutput')
                ->willReturn($jsonOutput);

        $instance = $this->getMockBuilder(AwsResources::class)
                         ->setConstructorArgs(['example', 'dev', $this->mockSdk()])
                         ->setMethods(['newProcess'])
                         ->getMock();

        $instance->expects($this->once())
                 ->method('newProcess')
                 ->with($expectedSshCommand)
                 ->willReturn($process);

        if (!$expected) {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
            $instance->dockerContainerId;
        } else {
            $actual = $instance->dockerContainerId;
            $this->assertEquals($expected, $actual);
        }
    }

    public function testNewProcess()
    {
        $process = $this->subject->newProcess('ssh');
        $this->assertInstanceOf(Process::class, $process);
        $this->assertEquals('ssh', $process->getCommandLine());
    }

    public function testStackIsActiveForNonExistentStack()
    {
        $cloudformation = $this->sdk->createCloudFormation();
        $cloudformation->expects($this->once())
                       ->method('describeStacks')
                       ->willThrowException(
                           new CloudFormationException(
                               "Stack with id {$this->subject->stackName} does not exist",
                               $this->createMock(CommandInterface::class)
                           )
                       );

        $this->assertEquals(false, $this->subject->stackIsActive);
    }

    public function testStackIsActiveWithUnexpectedException()
    {
        $cloudformation = $this->sdk->createCloudFormation();
        $cloudformation->expects($this->once())
                       ->method('describeStacks')
                       ->willThrowException(
                           new CloudFormationException(
                               "Something bad happened and all I got was this lousy exception",
                               $this->createMock(CommandInterface::class)
                           )
                       );

        $this->expectException(CloudFormationException::class);
        $this->subject->stackIsActive;
    }

    public function testStackIsActiveWithActiveStack()
    {
        $cloudformation = $this->sdk->createCloudFormation();
        $cloudformation->expects($this->once())
                       ->method('describeStacks')
                       ->willReturn(new Result([
                           'Stacks' => [
                               0 => [
                                   'StackName' => 'example-dev',
                                   'Parameters' => [
                                       [
                                           'ParameterKey' => 'AppName',
                                           'ParameterValue' => 'example',
                                       ],
                                       [
                                           'ParameterKey' => 'Active',
                                           'ParameterValue' => 'true',
                                       ],
                                       [
                                           'ParameterKey' => 'Environment',
                                           'ParameterValue' => 'development',
                                       ],
                                   ],
                               ],
                               // Some response fields omitted for brevity
                           ],
                       ]));

        $this->assertEquals(true, $this->subject->stackIsActive);
    }

    public function testStackIsActiveWithInactiveStack()
    {
        $cloudformation = $this->sdk->createCloudFormation();
        $cloudformation->expects($this->once())
                       ->method('describeStacks')
                       ->willReturn(new Result([
                           'Stacks' => [
                               0 => [
                                   'StackName' => 'example-dev',
                                   'Parameters' => [
                                       [
                                           'ParameterKey' => 'AppName',
                                           'ParameterValue' => 'example',
                                       ],
                                       [
                                           'ParameterKey' => 'Active',
                                           'ParameterValue' => 'false',
                                       ],
                                       [
                                           'ParameterKey' => 'Environment',
                                           'ParameterValue' => 'development',
                                       ],
                                   ],
                               ],
                               // Some response fields omitted for brevity
                           ],
                       ]));

        $this->assertEquals(false, $this->subject->stackIsActive);
    }

    protected function mockSdk()
    {
        $sdk = $this->createPartialMock(Sdk::class, [
            'createEcs',
            'createEc2',
            'createCloudFormation',
        ]);

        $mockMethods = [
            'createEcs' => $this->mockEcs(),
            'createEc2' => $this->mockEc2(),
            'createCloudFormation' => $this->mockCloudFormation(),
        ];

        foreach ($mockMethods as $method => $return) {
            $sdk->expects($this->any())
                ->method($method)
                ->willReturn($return);
        }

        return $sdk;
    }

    protected function mockEcs()
    {
        $mock = $this->createPartialMock(EcsClient::class, [
            'listTasks',
            'describeTasks',
            'describeContainerInstances',
        ]);

        $mock->expects($this->any())
             ->method('listTasks')
             ->willReturn(new Result([
                 'taskArns' => [
                     'arn:aws:ecs:eu-west-2:000000000000:task/4a0223a8-a99a-45dc-981b-1fccc27a8cc2',
                     'arn:aws:ecs:eu-west-2:000000000000:task/a4dd317c-3048-4524-9956-1261b6a3672e',
                 ]
             ]));

        $mock->expects($this->any())
             ->method('describeTasks')
             ->willReturn(new Result([
                 'tasks' => [
                     [
                         'taskArn' => 'arn:aws:ecs:eu-west-2:000000000000:task/4a0223a8-a99a-45dc-981b-1fccc27a8cc2',
                         'clusterArn' => 'arn:aws:ecs:eu-west-2:000000000000:cluster/wp-dev',
                         'taskDefinitionArn' => 'arn:aws:ecs:eu-west-2:000000000000:task-definition/example-dev:27',
                         'containerInstanceArn' => 'arn:aws:ecs:eu-west-2:000000000000:container-instance/e5ceba05-515c-44f6-a71e-2cb987e166d8',
                         // Some response fields omitted for brevity
                     ]
                 ]
             ]));

        $mock->expects($this->any())
             ->method('describeContainerInstances')
             ->willReturn(new Result([
                 'containerInstances' => [
                     [
                         'containerInstanceArn' => 'arn:aws:ecs:eu-west-2:000000000000:container-instance/e5ceba05-515c-44f6-a71e-2cb987e166d8',
                         'ec2InstanceId' => 'i-1e76111db12c0b9f0',
                         // Some response fields omitted for brevity
                     ]
                 ]
             ]));

        return $mock;
    }

    protected function mockEc2()
    {
        $mock = $this->createPartialMock(Ec2Client::class, [
            'describeInstances',
        ]);

        $mock->expects($this->any())
             ->method('describeInstances')
             ->willReturn(new Result([
                 'Reservations' => [
                     [
                         'Groups' => [],
                         'Instances' => [
                             [
                                 'ImageId' => 'ami-2218f945',
                                 'InstanceId' => 'i-1e76111db12c0b9f0',
                                 'PrivateDnsName' => 'ip-10-0-1-1.eu-west-2.compute.internal',
                                 'PrivateIpAddress' => '10.0.1.1',
                                 'PublicDnsName' => 'ec2-61-18-95-225.eu-west-2.compute.amazonaws.com',
                                 'PublicIpAddress' => '61.18.95.225',
                                 // Some response fields omitted for brevity
                             ]
                         ]
                     ]
                 ]
             ]));

        return $mock;
    }

    protected function mockCloudFormation()
    {
        $mock = $this->createPartialMock(CloudFormationClient::class, [
            'describeStackResource',
            'describeStacks',
        ]);

        $mockData = [
            'Storage' => new Result([
                'StackResourceDetail' => [
                    'StackName' => 'example-dev',
                    'LogicalResourceId' => 'Storage',
                    'PhysicalResourceId' => 'example-dev-bucket',
                    'ResourceType' => 'AWS::S3::Bucket',
                    // Some response fields omitted for brevity
                ],
            ]),
            'WebService' => new Result([
                'StackResourceDetail' => [
                    'StackName' => 'example-dev',
                    'LogicalResourceId' => 'WebService',
                    'PhysicalResourceId' => 'arn:aws:ecs:eu-west-2:000000000000:service/example-dev-WebService-XXXXXXXXXXXXX',
                    'ResourceType' => 'AWS::ECS::Service',
                    // Some response fields omitted for brevity
                ],
            ]),
        ];

        $mock->expects($this->any())
             ->method('describeStackResource')
             ->willReturnCallback(function($args) use ($mockData) {
                 $key = $args['LogicalResourceId'];
                 return $mockData[$key];
             });

        return $mock;
    }
}
