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
        $this->assertTrue($definition->hasArgument('source'));
        $this->assertTrue($definition->hasArgument('destination'));
        $this->assertEquals(2, $definition->getArgumentRequiredCount());
    }

    public function testExecuteWithIdenticalFromAndToArguments()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"source" and "destination" arguments cannot be the same');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'command'     => $this->command->getName(),
            'source'      => 'example:dev',
            'destination' => 'example:dev',
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
        // Test 1 : success
        $commandTester->execute([
            'command'     => $this->command->getName(),
            'source'      => 'example:staging',
            'destination' => 'example:dev',
        ]);

        $successMessage = 'Success: Migrated example:staging to example:dev';
        $this->assertContains($successMessage, $commandTester->getDisplay());

        // Test 2 : failure
        $this->expectException("Exception");
        $this->expectExceptionCode(100);
        $commandTester->execute([
            'command'     => $this->command->getName(),
            'source'      => 'example:dev',
            'destination' => 'example:staging',
        ]);
        $this->expectExceptionMessage('Operation cancelled: Instance identifier "staging" is not valid for a migrate destination');
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
