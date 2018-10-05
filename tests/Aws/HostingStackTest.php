<?php

namespace WpEcs\Tests\Aws;

use PHPUnit\Framework\TestCase;
use WpEcs\Aws\HostingStack;

class HostingStackTest extends TestCase
{
    public function stackNameDataProvider()
    {
        return [
            ['example-dev',     'example',  'dev'    ],
            ['example-staging', 'example',  'staging'],
            ['example-prod',    'example',  'prod'   ],
            ['example2-dev',    'example2', 'dev'    ],
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
            'StackId' => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/example-dev/c96d3035-458a-5ae5-ada3-ee273c59e65a',
            'StackName' => $stackName,
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
            'StackStatus' => 'UPDATE_COMPLETE',
            // Some response fields omitted for brevity
        ];

        $stack = new HostingStack($stackDescription);

        $this->assertEquals($expectedAppName, $stack->appName);
        $this->assertEquals($expectedEnv, $stack->env);
    }

    public function testAnActiveStack()
    {
        $stackDescription = [
            'StackId' => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/example-dev/c96d3035-458a-5ae5-ada3-ee273c59e65a',
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
            'StackStatus' => 'UPDATE_COMPLETE',
            // Some response fields omitted for brevity
        ];

        $stack = new HostingStack($stackDescription);

        $this->assertEquals(true, $stack->isActive);
        $this->assertEquals(false, $stack->isUpdating);
    }

    public function testAnInactiveStack()
    {
        $stackDescription = [
            'StackId' => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/example-dev/c96d3035-458a-5ae5-ada3-ee273c59e65a',
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
            'StackStatus' => 'UPDATE_COMPLETE',
            // Some response fields omitted for brevity
        ];

        $stack = new HostingStack($stackDescription);

        $this->assertEquals(false, $stack->isActive);
        $this->assertEquals(false, $stack->isUpdating);
    }

    public function updatingStackDataProvider()
    {
        return [
            // [ stack status, is updating? ]
            ['CREATE_IN_PROGRESS', true ],
            ['UPDATE_IN_PROGRESS', true ],
            ['CREATE_COMPLETE',    false],
            ['UPDATE_COMPLETE',    false],
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
            'StackId' => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/example-dev/c96d3035-458a-5ae5-ada3-ee273c59e65a',
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
            'StackStatus' => $status,
            // Some response fields omitted for brevity
        ];

        $stack = new HostingStack($stackDescription);

        $this->assertEquals(true, $stack->isActive);
        $this->assertEquals($expect, $stack->isUpdating);
    }
}
