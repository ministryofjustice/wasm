<?php

namespace WpEcs\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
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
        $commandTester->execute([
            'command'     => $this->command->getName(),
            'source'      => 'example:dev',
            'destination' => 'example:staging',
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
    public function testMigrateProductionAndAnswerNo($noString)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Aborting');

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs([$noString]);
        $commandTester->execute([
            'command' => $this->command->getName(),
            'source' => 'example:dev',
            'destination' => 'example:prod'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertContains('Are you sure you want to do that?', $output);
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
    public function testMigrationProductionAndAnswerYes($yesString)
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
        $commandTester->setInputs([$yesString]);
        $commandTester->execute([
            'source' => 'example:dev',
            'destination' => 'example:prod'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertContains('Are you sure you want to do that?', $output);
        $this->assertContains("Success: Migrated example:dev to example:prod", $output);
    }
}
