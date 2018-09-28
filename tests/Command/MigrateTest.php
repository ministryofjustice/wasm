<?php

namespace WpEcs\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Command\Command;
use WpEcs\Command\Migrate;
use WpEcs\Service\Migration;
use WpEcs\Wordpress\AbstractInstance;

class MigrateTest extends TestCase
{
    use MockInstanceHelper;

    /**
     * @var Command
     */
    protected $command;

    public function setUp()
    {
        $this->setupMockInstance();

        $mockCmd = $this->getMockBuilder(Migrate::class)
                        ->setConstructorArgs([$this->instanceFactory])
                        ->setMethods(['newMigration'])
                        ->getMock();

        $application = new Application();
        $application->add($mockCmd);
        $this->command = $application->find('migrate');
    }

    public function testConfigure()
    {
        $this->assertInstanceOf(Migrate::class, $this->command);
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasArgument('from'));
        $this->assertTrue($definition->hasArgument('to'));
        $this->assertEquals(2, $definition->getArgumentRequiredCount());
    }

    public function testExecuteWithIdenticalFromAndToArguments()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"from" and "to" arguments cannot be the same');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'command' => $this->command->getName(),
            'from'    => 'example:dev',
            'to'      => 'example:dev',
        ]);
    }

    public function testExecute()
    {
        $migration = $this->createMock(Migration::class);
        $migration->expects($this->once())
                  ->method('migrate');

        $this->command->expects($this->once())
                      ->method('newMigration')
                      ->with(
                          $this->isInstanceOf(AbstractInstance::class),
                          $this->isInstanceOf(AbstractInstance::class),
                          $this->isInstanceOf(OutputInterface::class)
                      )
                      ->willReturn($migration);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'command' => $this->command->getName(),
            'from'    => 'example:dev',
            'to'      => 'example:staging',
        ]);

        $successMessage = 'Success: Migrated example:dev to example:staging';
        $this->assertContains($successMessage, $commandTester->getDisplay());
    }

    public function testNewMigration()
    {
        $subject = new Migrate($this->instanceFactory);
        $outputInterface = $this->createMock(OutputInterface::class);

        $migration = $subject->newMigration(
            $this->instanceFactory->create('example:dev'),
            $this->instanceFactory->create('example:staging'),
            $outputInterface
        );

        $this->assertInstanceOf(Migration::class, $migration);
    }
}