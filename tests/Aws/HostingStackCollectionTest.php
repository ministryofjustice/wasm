<?php

namespace WpEcs\Tests\Aws;

use Aws\CloudFormation\CloudFormationClient;
use PHPUnit\Framework\TestCase;
use WpEcs\Aws\HostingStack;
use WpEcs\Aws\HostingStackCollection;

class HostingStackCollectionTest extends TestCase
{
    public function testGetStacks()
    {
        $collection = new HostingStackCollection(
            $this->mockCloudFormationClient()
        );
        $stacks     = $collection->getStacks();

        $this->assertCount(count($this->validHostingStackDescriptions()), $stacks);
        $this->assertContainsOnlyInstancesOf(HostingStack::class, $stacks);

        // Convert HostingStack objects into arrays so we can easily assert their values
        $actualValues = array_map(function ($stack) {
            return json_decode(json_encode($stack), true);
        }, $stacks);

        $expectedValues = [
            [
                'appName'    => 'example',
                'env'        => 'dev',
                'isActive'   => true,
                'isUpdating' => false,
                'family'     => 'WordPress',
            ],
            [
                'appName'    => 'example',
                'env'        => 'staging',
                'isActive'   => true,
                'isUpdating' => false,
                'family'     => 'WordPress',
            ],
        ];

        foreach ($expectedValues as $expectedValue) {
            $this->assertContains($expectedValue, $actualValues);
        }
    }

    public function testGetStackValid()
    {
        $cloudformation = $this->createPartialMock(
            CloudFormationClient::class,
            ['describeStacks']
        );
        $cloudformation->expects($this->once())
                       ->method('describeStacks')
                       ->with(['StackName' => 'example-dev'])
                       ->willReturn([
                           'Stacks' => [
                               [
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
                                       [
                                           'ParameterKey' => 'DockerImage',
                                           'ParameterValue' => '000000000000.dkr.ecr.eu-west-2.amazonaws.com/wp/example:2c72c28-201810091200',
                                       ],
                                   ],
                                   'StackStatus' => 'UPDATE_COMPLETE',
                               ],
                           ],
                       ]);

        $collection = new HostingStackCollection($cloudformation);
        $stack = $collection->getStack('example-dev');

        $this->assertInstanceOf(HostingStack::class, $stack);
        $this->assertEquals('example', $stack->appName);
        $this->assertEquals('dev', $stack->env);
    }

    public function testGetStackInvalid()
    {
        $cloudformation = $this->createPartialMock(
            CloudFormationClient::class,
            ['describeStacks']
        );
        $cloudformation->expects($this->once())
                       ->method('describeStacks')
                       ->with(['StackName' => 'some-other-stack'])
                       ->willReturn([
                           'Stacks' => [
                               [
                                   'StackId' => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/some-other-stack/c96d3035-458a-5ae5-ada3-ee273c59e65a',
                                   'StackName' => 'some-other-stack',
                                   'Parameters' => [
                                       [
                                           'ParameterKey' => 'SomeParameter',
                                           'ParameterValue' => 'some value',
                                       ],
                                       [
                                           'ParameterKey' => 'SomeOtherParameter',
                                           'ParameterValue' => 'another value',
                                       ],
                                   ],
                                   'StackStatus' => 'CREATE_COMPLETE',
                               ],
                           ],
                       ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This is not a hosting stack');

        $collection = new HostingStackCollection($cloudformation);
        $collection->getStack('some-other-stack');
    }

    protected function validHostingStackDescriptions() {
        return [
            [
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
                    [
                        'ParameterKey' => 'DockerImage',
                        'ParameterValue' => '000000000000.dkr.ecr.eu-west-2.amazonaws.com/wp/example:2c72c28-201810091200',
                    ],
                ],
                'StackStatus' => 'UPDATE_COMPLETE',
            ],
            [
                'StackId' => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/example-staging/c96d3035-458a-5ae5-ada3-ee273c59e65a',
                'StackName' => 'example-staging',
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
                        'ParameterValue' => 'staging',
                    ],
                    [
                        'ParameterKey' => 'DockerImage',
                        'ParameterValue' => '000000000000.dkr.ecr.eu-west-2.amazonaws.com/wp/example:2c72c28-201810091200',
                    ],
                ],
                'StackStatus' => 'UPDATE_COMPLETE',
            ],
        ];
    }

    protected function invalidHostingStackDescriptions() {
        return [
            // The StackName doesn't fit our expected pattern
            [
                'StackId' => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/some-other-stack/c96d3035-458a-5ae5-ada3-ee273c59e65a',
                'StackName' => 'some-other-stack',
                'Parameters' => [
                    [
                        'ParameterKey' => 'SomeParameter',
                        'ParameterValue' => 'some value',
                    ],
                    [
                        'ParameterKey' => 'SomeOtherParameter',
                        'ParameterValue' => 'another value',
                    ],
                ],
                'StackStatus' => 'CREATE_COMPLETE',
            ],

            // This stack has the right name, but it's missing all expected parameters
            [
                'StackId' => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/example-staging/c96d3035-458a-5ae5-ada3-ee273c59e65a',
                'StackName' => 'example-staging',
                'Parameters' => [],
                'StackStatus' => 'UPDATE_COMPLETE',
            ],

            // This stack has the right name, but is missing the 'Active' parameter
            [
                'StackId' => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/another-dev/c96d3035-458a-5ae5-ada3-ee273c59e65a',
                'StackName' => 'another-dev',
                'Parameters' => [
                    [
                        'ParameterKey' => 'AppName',
                        'ParameterValue' => 'another',
                    ],
                    [
                        'ParameterKey' => 'Environment',
                        'ParameterValue' => 'development',
                    ],
                ],
                'StackStatus' => 'UPDATE_COMPLETE',
            ],

            // This stack has the right name, but is missing the 'AppName' parameter
            [
                'StackId' => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/another-staging/c96d3035-458a-5ae5-ada3-ee273c59e65a',
                'StackName' => 'another-staging',
                'Parameters' => [
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
            ],

            // This stack has the right name, but is missing the 'Environment' parameter
            [
                'StackId' => 'arn:aws:cloudformation:eu-west-2:000000000000:stack/another-staging/c96d3035-458a-5ae5-ada3-ee273c59e65a',
                'StackName' => 'another-staging',
                'Parameters' => [
                    [
                        'ParameterKey' => 'AppName',
                        'ParameterValue' => 'another',
                    ],
                    [
                        'ParameterKey' => 'Active',
                        'ParameterValue' => 'true',
                    ],
                ],
                'StackStatus' => 'UPDATE_COMPLETE',
            ],
        ];
    }

    protected function mockCloudFormationClient() {
        $mockStackDescriptions = array_merge(
            $this->validHostingStackDescriptions(),
            $this->invalidHostingStackDescriptions()
        );

        $mock = $this->createMock(CloudFormationClient::class);
        $mock->expects($this->once())
             ->method('getPaginator')
             ->with('DescribeStacks')
             ->willReturnCallback(function() use ($mockStackDescriptions) {
                 shuffle($mockStackDescriptions);
                 $chunked = array_chunk($mockStackDescriptions, 2);
                 return array_map(function($chunk) {
                     return ['Stacks' => $chunk];
                 }, $chunked);
             });

        return $mock;
    }
}
