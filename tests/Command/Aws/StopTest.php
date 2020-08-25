<?php

namespace WpEcs\Tests\Command\Aws;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
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
     * @param $instanceIdentifier
     * @param $stackName
     */
    public function testStopNonProduction($instanceIdentifier, $stackName)
    {
        $command       = $this->getSubject($stackName, true);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'instance' => $instanceIdentifier,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString("Success: $instanceIdentifier is being stopped", $output);
    }

    public function yesStrings()
    {
        return [
            ['yes'],
            ['y'],
        ];
    }

    /**
     * @param $yesString
     * @dataProvider yesStrings
     */
    public function testStopProductionAndAnswerYes($yesString)
    {
        $command = $this->getSubject('example-prod', true);
        $commandTester = new CommandTester($command);
        $commandTester->setInputs([$yesString]);
        $commandTester->execute([
            'instance' => 'example:prod',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('stop a production instance', $output);
        $this->assertStringContainsString('Are you sure you want to do that?', $output);
        $this->assertStringContainsString("Success: example:prod is being stopped", $output);
    }

    public function noStrings()
    {
        return [
            ['no'],
            ['n'],
        ];
    }

    /**
     * @param string $noString
     * @dataProvider noStrings
     */
    public function testStopProductionAndAnswerNo($noString)
    {
        $instanceIdentifier = 'example:prod';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Aborting');

        $command = $this->getSubject(false, false);
        $commandTester = new CommandTester($command);
        $commandTester->setInputs([$noString]);
        $commandTester->execute([
            'instance' => $instanceIdentifier,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Are you sure you want to do that?', $output);
    }

    /**
     * The command should stop a production instance, without interactive prompt,
     * when called with the `--production` command line option
     */
    public function testStopProductionWithCommandLineFlag()
    {
        $instanceIdentifier = 'example:prod';
        $stackName = 'example-prod';

        $command = $this->getSubject($stackName, true);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'instance' => $instanceIdentifier,
            '--production' => true,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString("Success: $instanceIdentifier is being stopped", $output);
    }

    /**
     * @param string|false $expectStackName Expect `HostingStackCollection::getStack` to be called with this stack name
     * @param bool $expectCallToStop Expect that `HostingStack::stop` is called once. If false, it should never be called.
     *
     * @return Command
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
