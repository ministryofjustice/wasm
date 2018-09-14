<?php

namespace WpEcs\Tests\Command\Db;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use WpEcs\Command\Db\Export;
use WpEcs\Tests\Command\MockInstanceHelper;

class ExportTest extends TestCase
{
    use MockInstanceHelper;

    /**
     * @var Export
     */
    protected $command;

    public function setUp()
    {
        $this->setupMockInstance();
        $this->instance->name = 'example-dev';
        $application = new Application();
        $application->add(new Export($this->instanceFactory));
        $this->command = $application->find('db:export');
    }

    public function testConfigure()
    {
        $this->assertInstanceOf(Export::class, $this->command);
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasArgument('instance'));
        $this->assertTrue($definition->hasArgument('filename'));
        $this->assertEquals(1, $definition->getArgumentRequiredCount());
    }

    public function testExecute()
    {
        $this->instance->expects($this->once())
                       ->method('exportDatabase')
                       ->with($this->callback('is_resource'))
                       ->willReturnCallback(function($fh) {
                           fwrite($fh, 'Exported database content');
                       });

        $vfs = vfsStream::setup();
        $filename = "{$vfs->url()}/database.sql";

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'command' => $this->command->getName(),
            'instance' => 'example:dev',
            'filename' => $filename,
        ]);

        $this->assertFileExists($filename);
        $this->assertEquals('Exported database content', file_get_contents($filename));
        $this->assertContains("Success: Database exported to $filename", $commandTester->getDisplay());

    }

    public function testGetFilenameWithoutFilenameArgument()
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->atLeastOnce())
              ->method('getArgument')
              ->with('filename')
              ->willReturn(null);

        $filename = $this->command->getFilename($input, $this->instance);
        // Should contain a current date & timestamp, but let's not cause race conditions by asserting against the current time
        $this->assertRegExp('/^example-dev-[\d]{4}-[\d]{2}-[\d]{2}-[\d]{6}\.sql$/', $filename);
    }

    public function testGetFilenameWithFilenameArgument()
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->atLeastOnce())
              ->method('getArgument')
              ->with('filename')
              ->willReturn('database.sql');

        $filename = $this->command->getFilename($input, $this->instance);
        $this->assertEquals('database.sql', $filename);
    }
}
