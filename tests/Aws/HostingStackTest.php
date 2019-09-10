<?php

namespace WpEcs\Tests\Aws;

use Aws\CloudFormation\CloudFormationClient;
use PHPUnit\Framework\TestCase;
use WpEcs\Aws\HostingStack;

class HostingStackTest extends TestCase
{
    public function stackNameDataProvider()
    {
        return [
            ['example-dev', 'example', 'dev'],
            ['example-staging', 'example', 'staging'],
            ['example2-dev', 'example2', 'dev'],
        ];
    }

    /**
     * @param string $stackName
     * @param string $expectedAppName
     * @param string $expectedEnv
     *
     * @dataProvider stackNameDataProvider
     */
    public function testAppNameAndEnv($stackName, $expectedAppName, $expectedEnv)
    {
        $stackDescription = [
            'StackId'     => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/example-dev/c96d3035-458a-5ae5-ada3-ee273c59e65a',
            'StackName'   => $stackName,
            'Parameters'  => [
                [
                    'ParameterKey'   => 'AppName',
                    'ParameterValue' => 'example',
                ],
                [
                    'ParameterKey'   => 'Active',
                    'ParameterValue' => 'true',
                ],
                [
                    'ParameterKey'   => 'Environment',
                    'ParameterValue' => 'development',
                ],
                [
                    'ParameterKey'   => 'DockerImage',
                    'ParameterValue' => '000000000000.dkr.ecr.eu-west-2.amazonaws.com/wp/example:2c72c28-201810091200',
                ],
            ],
            'StackStatus' => 'UPDATE_COMPLETE',
            // Some response fields omitted for brevity
        ];

        $stack = new HostingStack($stackDescription, $this->mockCloudFormationClient());

        $this->assertEquals($expectedAppName, $stack->appName);
        $this->assertEquals($expectedEnv, $stack->env);
    }

    public function testAnActiveStack()
    {
        $stackDescription = [
            'StackId'     => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/example-dev/c96d3035-458a-5ae5-ada3-ee273c59e65a',
            'StackName'   => 'example-dev',
            'Parameters'  => [
                [
                    'ParameterKey'   => 'AppName',
                    'ParameterValue' => 'example',
                ],
                [
                    'ParameterKey'   => 'Active',
                    'ParameterValue' => 'true',
                ],
                [
                    'ParameterKey'   => 'Environment',
                    'ParameterValue' => 'development',
                ],
                [
                    'ParameterKey'   => 'DockerImage',
                    'ParameterValue' => '000000000000.dkr.ecr.eu-west-2.amazonaws.com/wp/example:2c72c28-201810091200',
                ],
            ],
            'StackStatus' => 'UPDATE_COMPLETE',
            // Some response fields omitted for brevity
        ];

        $stack = new HostingStack($stackDescription, $this->mockCloudFormationClient());

        $this->assertEquals(true, $stack->isActive);
        $this->assertEquals(false, $stack->isUpdating);
    }

    public function testAnInactiveStack()
    {
        $stackDescription = [
            'StackId'     => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/example-dev/c96d3035-458a-5ae5-ada3-ee273c59e65a',
            'StackName'   => 'example-dev',
            'Parameters'  => [
                [
                    'ParameterKey'   => 'AppName',
                    'ParameterValue' => 'example',
                ],
                [
                    'ParameterKey'   => 'Active',
                    'ParameterValue' => 'false',
                ],
                [
                    'ParameterKey'   => 'Environment',
                    'ParameterValue' => 'development',
                ],
                [
                    'ParameterKey'   => 'DockerImage',
                    'ParameterValue' => '000000000000.dkr.ecr.eu-west-2.amazonaws.com/wp/example:2c72c28-201810091200',
                ],
            ],
            'StackStatus' => 'UPDATE_COMPLETE',
            // Some response fields omitted for brevity
        ];

        $stack = new HostingStack($stackDescription, $this->mockCloudFormationClient());

        $this->assertEquals(false, $stack->isActive);
        $this->assertEquals(false, $stack->isUpdating);
    }

    public function updatingStackDataProvider()
    {
        return [
            // [ stack status, is updating? ]
            ['CREATE_IN_PROGRESS', true],
            ['UPDATE_IN_PROGRESS', true],
            ['CREATE_COMPLETE', false],
            ['UPDATE_COMPLETE', false],
        ];
    }

    /**
     * @param string $status The stack status
     * @param bool $expect Expected value for $stack->isUpdating
     *
     * @dataProvider updatingStackDataProvider
     */
    public function testStackIsUpdating($status, $expect)
    {
        $stackDescription = [
            'StackId'     => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/example-dev/c96d3035-458a-5ae5-ada3-ee273c59e65a',
            'StackName'   => 'example-dev',
            'Parameters'  => [
                [
                    'ParameterKey'   => 'AppName',
                    'ParameterValue' => 'example',
                ],
                [
                    'ParameterKey'   => 'Active',
                    'ParameterValue' => 'true',
                ],
                [
                    'ParameterKey'   => 'Environment',
                    'ParameterValue' => 'development',
                ],
                [
                    'ParameterKey'   => 'DockerImage',
                    'ParameterValue' => '000000000000.dkr.ecr.eu-west-2.amazonaws.com/wp/example:2c72c28-201810091200',
                ],
            ],
            'StackStatus' => $status,
            // Some response fields omitted for brevity
        ];

        $stack = new HostingStack($stackDescription, $this->mockCloudFormationClient());

        $this->assertEquals(true, $stack->isActive);
        $this->assertEquals($expect, $stack->isUpdating);
    }

    public function stackFamilyDataProvider()
    {
        return [
            // [ docker image uri, app family ]
            ['000000000000.dkr.ecr.eu-west-2.amazonaws.com/wp/example:2c72c28-201810091200', 'WordPress'],
            ['000000000000.dkr.ecr.eu-west-2.amazonaws.com/tp-java/example:2c72c28-201810091200', 'Java'],
            ['000000000000.dkr.ecr.eu-west-2.amazonaws.com/example:2c72c28-201810091200', 'Unknown'],
            [false, 'Unknown'],
        ];
    }

    /**
     * @param string $dockerImage The stack's docker image URI
     * @param string $expect Expected value for $stack->family
     *
     * @dataProvider stackFamilyDataProvider
     */
    public function testStackFamily($dockerImage, $expect)
    {
        $stackDescription = [
            'StackId'     => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/example-dev/c96d3035-458a-5ae5-ada3-ee273c59e65a',
            'StackName'   => 'example-dev',
            'Parameters'  => [
                [
                    'ParameterKey'   => 'AppName',
                    'ParameterValue' => 'example',
                ],
                [
                    'ParameterKey'   => 'Active',
                    'ParameterValue' => 'true',
                ],
                [
                    'ParameterKey'   => 'Environment',
                    'ParameterValue' => 'development',
                ],
            ],
            'StackStatus' => 'UPDATE_COMPLETE',
            // Some response fields omitted for brevity
        ];

        if ($dockerImage) {
            $stackDescription['Parameters'][] = [
                'ParameterKey'   => 'DockerImage',
                'ParameterValue' => $dockerImage,
            ];
        }

        $stack = new HostingStack($stackDescription, $this->mockCloudFormationClient());

        $this->assertEquals($expect, $stack->family);
    }

    public function testStart()
    {
        $stackDescription = [
            'StackId'     => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/example-dev/c96d3035-458a-5ae5-ada3-ee273c59e65a',
            'StackName'   => 'example-dev',
            'Parameters'  => [
                [
                    'ParameterKey'   => 'AppName',
                    'ParameterValue' => 'example',
                ],
                [
                    'ParameterKey'   => 'Active',
                    'ParameterValue' => 'false',
                ],
                [
                    'ParameterKey'   => 'Environment',
                    'ParameterValue' => 'development',
                ],
            ],
            'StackStatus' => 'UPDATE_COMPLETE',
            // Some response fields omitted for brevity
        ];

        $cloudformation = $this->createPartialMock(
            CloudFormationClient::class,
            ['updateStack']
        );

        $cloudformation->expects($this->once())
                       ->method('updateStack')
                       ->with([
                           'StackName'           => 'example-dev',
                           'UsePreviousTemplate' => true,
                           'Capabilities'        => ['CAPABILITY_IAM'],
                           'Parameters'          => [
                               [
                                   'ParameterKey'     => 'AppName',
                                   'UsePreviousValue' => true,
                               ],
                               [
                                   'ParameterKey'   => 'Active',
                                   'ParameterValue' => 'true',
                               ],
                               [
                                   'ParameterKey'     => 'Environment',
                                   'UsePreviousValue' => true,
                               ],
                           ],
                       ]);

        $stack = new HostingStack($stackDescription, $cloudformation);

        $stack->start();
    }

    public function testStop()
    {
        $stackDescription = [
            'StackId'     => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/example-dev/c96d3035-458a-5ae5-ada3-ee273c59e65a',
            'StackName'   => 'example-dev',
            'Parameters'  => [
                [
                    'ParameterKey'   => 'AppName',
                    'ParameterValue' => 'example',
                ],
                [
                    'ParameterKey'   => 'Active',
                    'ParameterValue' => 'true',
                ],
                [
                    'ParameterKey'   => 'Environment',
                    'ParameterValue' => 'development',
                ],
            ],
            'StackStatus' => 'UPDATE_COMPLETE',
            // Some response fields omitted for brevity
        ];

        $cloudformation = $this->createPartialMock(
            CloudFormationClient::class,
            ['updateStack']
        );

        $cloudformation->expects($this->once())
                       ->method('updateStack')
                       ->with([
                           'StackName'           => 'example-dev',
                           'UsePreviousTemplate' => true,
                           'Capabilities'        => ['CAPABILITY_IAM'],
                           'Parameters'          => [
                               [
                                   'ParameterKey'     => 'AppName',
                                   'UsePreviousValue' => true,
                               ],
                               [
                                   'ParameterKey'   => 'Active',
                                   'ParameterValue' => 'false',
                               ],
                               [
                                   'ParameterKey'     => 'Environment',
                                   'UsePreviousValue' => true,
                               ],
                           ],
                       ]);

        $stack = new HostingStack($stackDescription, $cloudformation);

        $stack->stop();
    }

    public function testStartAnAlreadyRunningStack()
    {
        $stackDescription = [
            'StackId'     => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/example-dev/c96d3035-458a-5ae5-ada3-ee273c59e65a',
            'StackName'   => 'example-dev',
            'Parameters'  => [
                [
                    'ParameterKey'   => 'AppName',
                    'ParameterValue' => 'example',
                ],
                [
                    'ParameterKey'   => 'Active',
                    'ParameterValue' => 'true',
                ],
                [
                    'ParameterKey'   => 'Environment',
                    'ParameterValue' => 'development',
                ],
            ],
            'StackStatus' => 'UPDATE_COMPLETE',
            // Some response fields omitted for brevity
        ];

        $cloudformation = $this->createPartialMock(
            CloudFormationClient::class,
            ['updateStack']
        );

        $cloudformation->expects($this->never())
                       ->method('updateStack');

        $stack = new HostingStack($stackDescription, $cloudformation);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This stack is already running');

        $stack->start();
    }

    public function testStopAnAlreadyStoppedStack()
    {
        $stackDescription = [
            'StackId'     => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/example-dev/c96d3035-458a-5ae5-ada3-ee273c59e65a',
            'StackName'   => 'example-dev',
            'Parameters'  => [
                [
                    'ParameterKey'   => 'AppName',
                    'ParameterValue' => 'example',
                ],
                [
                    'ParameterKey'   => 'Active',
                    'ParameterValue' => 'false',
                ],
                [
                    'ParameterKey'   => 'Environment',
                    'ParameterValue' => 'development',
                ],
            ],
            'StackStatus' => 'UPDATE_COMPLETE',
            // Some response fields omitted for brevity
        ];

        $cloudformation = $this->createPartialMock(
            CloudFormationClient::class,
            ['updateStack']
        );

        $cloudformation->expects($this->never())
                       ->method('updateStack');

        $stack = new HostingStack($stackDescription, $cloudformation);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This stack is already stopped');

        $stack->stop();
    }

    protected function mockCloudFormationClient()
    {
        return $this->createMock(CloudFormationClient::class);
    }
}
