<?php

namespace WpEcs\Tests\Command\Aws;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tests\Helper\TableTest;
use WpEcs\Aws\HostingStack;
use WpEcs\Aws\HostingStackCollection;
use WpEcs\Command\Aws\Stacks;

class StacksTest extends TestCase
{
    /**
     * @var Command
     */
    protected $command;

    public function setUp()
    {
        $application = new Application();
        $command = new Stacks($this->mockHostingStackCollection());
        $application->add($command);
        $this->command = $application->find('aws:stacks');
    }

    public function testConfigure()
    {
        $this->assertInstanceOf(Stacks::class, $this->command);
    }

    public function testExecute()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'command' => $this->command->getName(),
        ]);

        $output = $commandTester->getDisplay();
        $outputLines = explode("\n", $output);

        $this->assertEquals("Found 2 apps:", $outputLines[0]);

        // Assert that the output looks like it contains a table
        $this->assertStringStartsWith('+---', $outputLines[1]);
        $this->assertStringStartsWith('| App Name ', $outputLines[2]);
        $this->assertStringStartsWith('+---', $outputLines[3]);
        $this->assertStringStartsWith('| another ', $outputLines[4]);
        $this->assertStringStartsWith('| example ', $outputLines[5]);
        $this->assertStringStartsWith('+---', $outputLines[6]);

        // Assert that the table header row contains the expected columns:
        // | App Name | Family | Dev | Staging | Production |
        $this->assertRegExp('/^\| App Name +\| Family +\| Dev +\| Staging +\| Production + \|$/', $outputLines[2]);
    }

    public function testFormatTableData() {
        $stacks = $this->mockStacks();
        $actual = $this->command->formatTableData($stacks);

        // The correct number of data rows are returned
        $this->assertCount(2, $actual);

        // The expected rows exist and contain the correct data
        $this->assertContains([
            'appName' => 'example',
            'family'  => 'WordPress',
            'dev'     => '<fg=green>Running</>',
            'staging' => '<fg=blue>Updating</>',
            'prod'    => '<fg=green>Running</>',
        ], $actual);
        $this->assertContains([
            'appName' => 'another',
            'family'  => 'Java',
            'dev'     => '<fg=green>Running</>',
            'staging' => '<fg=red>Stopped</>',
            'prod'    => '<fg=blue>Not Deployed</>',
        ], $actual);

        // Rows are sorted alphabetically by application name (which is the key)
        $this->assertEquals(['another', 'example'], array_keys($actual));
    }

    protected function mockHostingStackCollection() {
        $mock = $this->createMock(HostingStackCollection::class);
        $mock->expects($this->any())
             ->method('getStacks')
             ->willReturn($this->mockStacks());
        return $mock;
    }

    protected function mockStacks() {
        $stacks = [
            [
                'appName'    => 'example',
                'env'        => 'dev',
                'family'     => 'WordPress',
                'isActive'   => true,
                'isUpdating' => false,
            ],
            [
                'appName'    => 'example',
                'env'        => 'staging',
                'family'     => 'WordPress',
                'isActive'   => true,
                'isUpdating' => true,
            ],
            [
                'appName'    => 'example',
                'env'        => 'prod',
                'family'     => 'WordPress',
                'isActive'   => true,
                'isUpdating' => false,
            ],
            [
                'appName'    => 'another',
                'env'        => 'dev',
                'family'     => 'Java',
                'isActive'   => true,
                'isUpdating' => false,
            ],
            [
                'appName'    => 'another',
                'env'        => 'staging',
                'family'     => 'Java',
                'isActive'   => false,
                'isUpdating' => false,
            ],
        ];

        return array_map(function($stack) {
            $mock = $this->createMock(HostingStack::class);
            $mock->appName = $stack['appName'];
            $mock->env = $stack['env'];
            $mock->family = $stack['family'];
            $mock->isActive = $stack['isActive'];
            $mock->isUpdating = $stack['isUpdating'];
            return $mock;
        }, $stacks);
    }
}
