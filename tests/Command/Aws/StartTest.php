<?php

namespace WpEcs\Tests\Command\Aws;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use WpEcs\Aws\HostingStack;
use WpEcs\Aws\HostingStackCollection;
use WpEcs\Command\Aws\Start;

class StartTest extends TestCase
{
    public function testConfigure()
    {
        $command = $this->getSubject(false);
        $this->assertInstanceOf(Start::class, $command);
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('instance'));
        $this->assertEquals(1, $definition->getArgumentRequiredCount());
    }

    public function executeDataProvider() {
        return [
            // instance identifier, stack name
            ['example:dev',     'example-dev'    ],
            ['example:staging', 'example-staging'],
            ['example:prod',    'example-prod'   ],
        ];
    }

    /**
     * @dataProvider executeDataProvider
     * @param $instanceIdentifier
     * @param $stackName
     */
    public function testStop($instanceIdentifier, $stackName)
    {
        $command = $this->getSubject($stackName);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'instance' => $instanceIdentifier,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString("Success: $instanceIdentifier is starting", $output);
    }

    /**
     * @param string|false $expectStackName Expect `HostingStackCollection::getStack` to be called with this stack name
     *
     * @return Command
     */
    protected function getSubject($expectStackName)
    {
        $hostingStack = $this->createMock(HostingStack::class);
        $hostingStack->expects($expectStackName ? $this->once() : $this->never())
                     ->method('start');

        $collection = $this->createMock(HostingStackCollection::class);
        $collection->expects($expectStackName ? $this->once() : $this->never())
                   ->method('getStack')
                   ->with($expectStackName)
                   ->willReturn($hostingStack);

        $application = new Application();
        $command = new Start($collection);
        $application->add($command);
        return $application->find('aws:start');
    }
}
