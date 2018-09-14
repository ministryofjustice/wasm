<?php

namespace WpEcs\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Command\Command;
use WpEcs\Command\Exec;

class ExecTest extends TestCase
{
    use MockInstanceHelper;

    /**
     * @var Command
     */
    protected $command;

    public function setUp()
    {
        $this->setupMockInstance();
        $application = new Application();
        $application->add(new Exec($this->instanceFactory));
        $this->command = $application->find('exec');
    }

    public function testConfigure()
    {
        $this->assertInstanceOf(Exec::class, $this->command);
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasArgument('instance'));
        $this->assertTrue($definition->hasArgument('cmd'));
        $this->assertEquals(2, $definition->getArgumentRequiredCount());
    }

    public function testExecute()
    {
        $this->instance->expects($this->once())
                       ->method('execute')
                       ->with('pwd')
                       ->willReturn("/current/directory\n");

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'command' => $this->command->getName(),
            'instance' => 'example:dev',
            'cmd' => 'pwd',
        ]);

        $this->assertEquals("/current/directory\n", $commandTester->getDisplay());
    }
}
