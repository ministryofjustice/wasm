<?php
namespace WpEcs\Tests\Wordpress;

use PHPUnit\Framework\TestCase;
use WpEcs\Wordpress\AbstractInstance;
use Symfony\Component\Process\Process;
use org\bovigo\vfs\vfsStream;

class AbstractInstanceTest extends TestCase
{
    public function testEnv()
    {
        $instance = $this->newInstance(['execute']);

        $instance->expects($this->once())
            ->method('execute')
            ->with('printenv SERVER_NAME')
            ->willReturn("example.com\n");

        for ($i = 0; $i < 3; $i++) {
            // The first call to $instance->env() should trigger $instance->execute() and cache the response
            // Subsequent calls should use the cache, so $instance->execute() is only ever called once
            $actual = $instance->env('SERVER_NAME');
            $this->assertEquals('example.com', $actual);
        }
    }

    public function testExecute()
    {
        $command = 'command-to-execute';
        $output = 'Output from command';

        $process = $this->createMock(Process::class);
        $process->expects($this->once())
            ->method('mustRun')
            ->willReturnSelf();
        $process->expects($this->atLeastOnce())
            ->method('getOutput')
            ->willReturn($output);

        $instance = $this->newInstance(['newCommand']);
        $instance->expects($this->atLeastOnce())
            ->method('newCommand')
            ->with($command)
            ->willReturn($process);

        $actualOutput = $instance->execute($command);
        $this->assertEquals($output, $actualOutput);
    }

    public function testImportDatabase()
    {
        $structure = ['database.sql' => 'SQL file content'];
        $vfs = vfsStream::setup('root', null, $structure);
        $fileHandle = fopen("{$vfs->url()}/database.sql", 'r');

        $process = $this->createMock(Process::class);
        $process->expects($this->at(0))
            ->method('setInput')
            ->with($fileHandle)
            ->willReturnSelf();

        $process->expects($this->at(1))
            ->method('mustRun')
            ->willReturnSelf();

        $instance = $this->newInstance(['detectNetwork', 'newCommand']);

        $instance->expects($this->atLeastOnce())
            ->method('newCommand')
            ->with('wp --allow-root db import -', ['-i'])
            ->willReturn($process);

        $instance->importDatabase($fileHandle);
    }

    public function testExportDatabase()
    {
        $vfs = vfsStream::setup('root', null);
        $exportPath = "{$vfs->url()}/database.sql";
        $errorPath = "{$vfs->url()}/stderr";
        $fileHandle = fopen($exportPath, 'w');
        $err = fopen($errorPath, 'w');

        $exportData = 'Output from "wp db export" command which should be written to the SQL file';
        $errorMessage = 'An example error message sent to stderr';

        $process = $this->createMock(Process::class);

        $process->expects($this->atLeastOnce())
            ->method('mustRun')
            ->with($this->callback('is_callable'))
            ->willReturnCallback(function ($callback) use ($exportData, $errorMessage) {
                // Output some data to stderr
                $callback(Process::ERR, $errorMessage);

                // Split export data into chunks to emulate command output being streamed to the callback via multiple invocations
                $exportChunks = str_split($exportData, 20);
                foreach ($exportChunks as $chunk) {
                    $callback(Process::OUT, $chunk);
                }
            });

        //$instance = $this->newInstance(['detectNetwork', 'newCommand', 'getBlogId']);
        $instance = $this->getMockBuilder(AbstractInstance::class)
            ->disableOriginalConstructor()
            ->setMethods(['detectNetwork', 'newCommand'])
            ->getMockForAbstractClass();

        $instance->expects($this->atLeastOnce())
            ->method('newCommand')
            ->with('wp --allow-root db export -')
            ->willReturn($process);

        $instance->exportDatabase($fileHandle, $err);

        fclose($fileHandle);
        fclose($err);

        $fileContents = file_get_contents($exportPath);
        $this->assertEquals($exportData, $fileContents);

        $errorContents = file_get_contents($errorPath);
        $this->assertEquals($errorMessage, $errorContents);
    }

    /**
     * Create a mock AbstractInstance object
     *
     * @param array $mockMethods Methods to mock
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     * @throws \ReflectionException
     */
    private function newInstance($mockMethods = [])
    {
        return $this->getMockForAbstractClass(
            AbstractInstance::class,
            [],
            '',
            false,
            true,
            true,
            $mockMethods
        );
    }
}
