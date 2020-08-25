<?php

namespace WpEcs\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;
use WpEcs\Command\Shell;

class ShellTest extends TestCase
{
    use MockInstanceHelper;

    /**
     * @var Command
     */
    protected $command;

    public function setUp(): void
    {
        $this->setupMockInstance();
        $application = new Application();
        $application->add(new Shell($this->instanceFactory));
        $this->command = $application->find('shell');
    }

    public function testConfigure()
    {
        $this->assertInstanceOf(Shell::class, $this->command);
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasArgument('instance'));
        $this->assertTrue($definition->hasArgument('shell'));
        $this->assertEquals(1, $definition->getArgumentRequiredCount());
    }

    /**
     * Execute the command WITHOUT the optional 'shell' parameter
     */
    public function testExecuteWithoutShellArgument()
    {
        $this->runExecuteTest(false);
    }

    /**
     * Execute the command WITH the optional 'shell' parameter
     */
    public function testExecuteWithShellArgument()
    {
        $this->runExecuteTest('sh');
    }

    protected function runExecuteTest($shellArgument)
    {
        $process = $this->createMock(Process::class);
        $process->expects($this->atLeastOnce())
                ->method('setTty')
                ->with(true)
                ->willReturnSelf();
        $process->expects($this->once())
                ->method('run')
                ->willReturn(0);

        $validDockerOptions = function($actual) {
            $terminal = new Terminal();
            $expected = [
                // TTY & input are enabled
                '-ti',

                // Terminal dimensions are passed through to the shell command as environment variables
                "-e COLUMNS={$terminal->getWidth()}",
                "-e LINES={$terminal->getHeight()}",
            ];

            sort($actual);
            sort($expected);

            $this->assertEquals($expected, $actual);
            return true;
        };

        $this->instance->expects($this->once())
                       ->method('newCommand')
                       ->with(
                           $shellArgument ?: 'bash',
                           $this->callback($validDockerOptions),
                           $this->contains('-t')
                       )
                       ->willReturn($process);

        $commandTester = new CommandTester($this->command);

        $arguments = ['instance' => 'example:dev'];
        if ($shellArgument) {
            $arguments['shell'] = $shellArgument;
        }

        $commandTester->execute(array_merge(
            ['command' => $this->command->getName()],
            $arguments
        ));
    }
}
