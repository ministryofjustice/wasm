<?php

namespace WpEcs\Tests\Command\Aws;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use WpEcs\Aws\HostingStack;
use WpEcs\Aws\HostingStackCollection;
use WpEcs\Command\Aws\Stop;

class StopTest extends TestCase
{
    public function testConfigure()
    {
        $command = $this->getSubject(false, false);
        $this->assertInstanceOf(Stop::class, $command);
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('instance'));
        $this->assertTrue($definition->hasOption('production'));
        $this->assertEquals(1, $definition->getArgumentRequiredCount());
    }

    public function executeDataProvider()
    {
        return [
            // instance identifier, stack name
            ['example:dev', 'example-dev'],
            ['example:staging', 'example-staging'],
        ];
    }

    /**
     * @dataProvider executeDataProvider
     */
    public function testStopNonProduction($instanceIdentifier, $stackName)
    {
        $command       = $this->getSubject($stackName, true);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'instance' => $instanceIdentifier,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertContains("Success: $instanceIdentifier is being stopped", $output);
    }

    public function yesStrings()
    {
        return [
            ['yes'],
            ['y'],
        ];
    }

    public function noStrings()
    {
        return [
            ['no'],
            ['n'],
        ];
    }

    /**
     * @param string|false $expectStackName Expect `HostingStackCollection::getStack` to be called with this stack name
     * @param bool $expectCallToStop Expect that `HostingStack::stop` is called once. If false, it should never be called.
     *
     * @return \Symfony\Component\Console\Command\Command
     */
    protected function getSubject($expectStackName, $expectCallToStop)
    {
        $hostingStack = $this->createMock(HostingStack::class);
        $hostingStack->expects($expectCallToStop ? $this->once() : $this->never())
                     ->method('stop');

        $collection = $this->createMock(HostingStackCollection::class);
        $collection->expects($expectStackName ? $this->once() : $this->never())
                   ->method('getStack')
                   ->with($expectStackName)
                   ->willReturn($hostingStack);

        $application = new Application();
        $command = new Stop($collection);
        $application->add($command);
        return $application->find('aws:stop');
    }
}
