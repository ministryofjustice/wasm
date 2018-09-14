<?php

namespace WpEcs\Tests\Command\Db;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use WpEcs\Command\Db\Import;
use WpEcs\Tests\Command\MockInstanceHelper;

class ImportTest extends TestCase
{
    use MockInstanceHelper;

    /**
     * @var Import
     */
    protected $command;

    public function setUp()
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
                       ->willReturnCallback(function($fh) {
                           $fileContent = fread($fh, 1024);
                           $this->assertEquals('Database content to import', $fileContent);
                       });

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'command' => $this->command->getName(),
            'instance' => 'example:dev',
            'filename' => $filename,
        ]);

        $this->assertContains("Success: Database imported from $filename", $commandTester->getDisplay());
    }
}
