<?php

namespace WpEcs\Tests\Command\Db;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Exception\RuntimeException;
use WpEcs\Command\Db\Import;
use WpEcs\Tests\Command\MockInstanceHelper;

class ImportTest extends TestCase
{
    use MockInstanceHelper;

    /**
     * @var Import
     */
    protected $command;

    public function setUp(): void
    {
        $this->setupMockInstance();
        $application = new Application();
        $application->add(new Import($this->instanceFactory));
        $this->command = $application->find('db:import');
    }

    public function testConfigure()
    {
        $this->assertInstanceOf(Import::class, $this->command);
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasArgument('instance'));
        $this->assertTrue($definition->hasArgument('filename'));
        $this->assertEquals(2, $definition->getArgumentRequiredCount());
    }

    public function testExecute()
    {
        $vfs = vfsStream::setup('root', null, [
            'database.sql' => 'Database content to import',
        ]);

        $filename = "{$vfs->url()}/database.sql";

        $this->instance->expects($this->once())
            ->method('importDatabase')
            ->with($this->callback('is_resource'))
            ->willReturnCallback(function ($fh) {
                $fileContent = fread($fh, 1024);
                $this->assertEquals('Database content to import', $fileContent);
            });

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'command' => $this->command->getName(),
            'instance' => 'example:dev',
            'filename' => $filename,
        ]);

        $this->assertStringContainsString("Success: Database imported from $filename", $commandTester->getDisplay());
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
    public function testImportProductionAndAnswerYes($yesString)
    {
        $vfs = vfsStream::setup('root', null, [
            'database.sql' => 'Database content to import',
        ]);

        $filename = "{$vfs->url()}/database.sql";

        $this->instance->expects($this->once())
            ->method('importDatabase')
            ->with($this->callback('is_resource'))
            ->willReturnCallback(function ($fh) {
                $fileContent = fread($fh, 1024);
                $this->assertEquals('Database content to import', $fileContent);
            });

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs([$yesString]);
        $commandTester->execute([
            'command' => $this->command->getName(),
            'instance' => 'example:prod',
            'filename' => $filename,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('import data to a production instance', $output);
        $this->assertStringContainsString('Are you sure you want to do that?', $output);
        $this->assertStringContainsString("Success: Database imported from $filename", $output);
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
    public function testImportProductionAndAnswerNo($noString)
    {
        $vfs = vfsStream::setup('root', null, [
            'database.sql' => 'Database content to import',
        ]);

        $filename = "{$vfs->url()}/database.sql";

        $this->instance->expects($this->never())
            ->method('importDatabase')
            ->with($this->callback('is_resource'))
            ->willReturnCallback(function ($fh) {
                $fileContent = fread($fh, 1024);
                $this->assertEquals('Database content to import', $fileContent);
            });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Aborting');

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs([$noString]);
        $commandTester->execute([
            'command' => $this->command->getName(),
            'instance' => 'example:prod',
            'filename' => $filename
        ]);
    }
}
