<?php

namespace WpEcs\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use WpEcs\Service\Migration;
use WpEcs\Wordpress\AbstractInstance;
use WpEcs\Wordpress\LocalInstance;
use WpEcs\Wordpress\AwsInstance;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Terminal;

class MigrationTest extends TestCase
{
    /**
     * @var OutputInterface
     */
    protected $output;

    protected function setUp(): void
    {
        $this->output = $this->createMock(OutputInterface::class);
    }

    public function testMigrate()
    {
        $migration = $this->createPartialMock(Migration::class, [
            'beginStep',
            'endStep',
            'checkCompatibility',
            'moveDatabase',
            'rewriteDatabase',
            'syncUploads',
        ]);

        $migration->expects($this->at(0))->method('beginStep')->with($this->stringContains('Checking compatibility'));
        $migration->expects($this->at(1))->method('checkCompatibility');
        $migration->expects($this->at(2))->method('endStep');

        $migration->expects($this->at(3))->method('beginStep')->with($this->stringContains('Moving database'));
        $migration->expects($this->at(4))->method('moveDatabase');
        $migration->expects($this->at(5))->method('endStep');

        $migration->expects($this->at(6))->method('beginStep')->with($this->stringContains('Rewriting database'));
        $migration->expects($this->at(7))->method('rewriteDatabase');
        $migration->expects($this->at(8))->method('endStep');

        $migration->expects($this->at(9))->method('beginStep')->with($this->stringContains('Syncing media uploads'));
        $migration->expects($this->at(10))->method('syncUploads');
        $migration->expects($this->at(11))->method('endStep');

        $migration->migrate();
    }

    public function testMoveDatabase()
    {
        /**
         * Is the given object a file handle (a.k.a. resource) with its
         * internal pointer at the beginning of the file?
         *
         * @param $fh
         *
         * @return bool
         */
        $isRewoundFile = function ($fileHandle) {
            return is_resource($fileHandle) && ftell($fileHandle) === 0;
        };

        $source = $this->mockInstance();
        $source->expects($this->once())
            ->method('exportDatabase')
            ->with($this->callback($isRewoundFile))
            ->willReturnCallback(function ($fh) {
                fwrite($fh, 'Exported database from source');
            });

        $dest = $this->mockInstance();
        $dest->expects($this->once())
            ->method('importDatabase')
            ->with($this->callback($isRewoundFile));

        $this->output->expects($this->exactly(2))
            ->method('writeln')
            ->withConsecutive(
                [$this->stringStartsWith('Exporting database'), OutputInterface::VERBOSITY_VERBOSE],
                [$this->stringStartsWith('Importing database'), OutputInterface::VERBOSITY_VERBOSE]
            );

        $migration = new Migration($source, $dest, $this->output);
        $migration->moveDatabase();
    }

    public function testDbSearchReplace()
    {
        $searchFor = 'search for this';
        $replaceWith = 'replace with this';

        $validCommand = function ($command) use ($searchFor, $replaceWith) {
            $this->assertEquals('wp', $command[0]);
            $this->assertEquals('search-replace', $command[1]);
            $this->assertEquals('--allow-root', $command[3]);

            $lastItem = count($command) - 1;

            $this->assertEquals($searchFor, $command[$lastItem - 1]);
            $this->assertEquals($replaceWith, $command[$lastItem]);

            return true;
        };

        $source = $this->mockInstance();
        $dest = $this->mockInstance();
        $dest->expects($this->once())
            ->method('execute')
            ->with($this->callback($validCommand));

        $migration = new Migration($source, $dest, $this->output);
        $migration->dbSearchReplace($searchFor, $replaceWith);
    }

    public function testRewriteDatabase()
    {
        $source = $this->mockInstance();
        $source->uploadsBaseUrl = 'http://example.docker/app/uploads';
        $source->expects($this->exactly(2))
            ->method('env')
            ->will($this->returnValueMap([
                ['WP_HOME', 'http://example.docker'],
                ['SERVER_NAME', 'example.docker'],
            ]));

        $dest = $this->mockInstance();
        $dest->uploadsBaseUrl = 'https://s3-eu-west-2.amazonaws.com/example-dev-storage/uploads';
        $dest->expects($this->exactly(2))
            ->method('env')
            ->will($this->returnValueMap([
                ['WP_HOME', 'https://example.dev.wp.dsd.io'],
                ['SERVER_NAME', 'example.dev.wp.dsd.io'],
            ]));

        $migration = $this->getMockBuilder(Migration::class)
            ->setConstructorArgs([$source, $dest, $this->output])
            ->setMethods(['dbSearchReplace'])
            ->getMock();

        // Search & replace terms become less specific with each consecutive call
        // This must happen in order, so that broad search & replace terms don't replace more specific terms
        $migration->expects($this->exactly(3))
            ->method('dbSearchReplace')
            ->withConsecutive(
                [
                    'http://example.docker/app/uploads',
                    'https://s3-eu-west-2.amazonaws.com/example-dev-storage/uploads',
                ],
                [
                    'http://example.docker',
                    'https://example.dev.wp.dsd.io',
                ],
                [
                    'example.docker',
                    'example.dev.wp.dsd.io',
                ]
            );

        $migration->rewriteDatabase();
    }

    public function syncUploadsDataProvider()
    {
        return [
            /*
             *  [
             *      [ Source Instance Type, Mock Uploads Path ],
             *      [ Destination Instance Type, Mock Uploads Path ],
             *  ]
             */
            [
                [LocalInstance::class, '/path/to/local-instance/web/app/uploads'],
                [AwsInstance::class, 's3://destination-bucket/uploads'],
            ],
            [
                [AwsInstance::class, 's3://source-bucket/uploads'],
                [LocalInstance::class, '/path/to/local-instance/web/app/uploads'],
            ],
            [
                [AwsInstance::class, 's3://source-bucket/uploads'],
                [AwsInstance::class, 's3://destination-bucket/uploads'],
            ],
            [
                [LocalInstance::class, '/path/to/local-instance/web/app/uploads'],
                [LocalInstance::class, '/a/different/local-instance/web/app/uploads'],
            ]
        ];
    }

    /**
     * @dataProvider syncUploadsDataProvider
     * @param array $sourceArgs
     * @param array $destArgs
     */
    public function testSyncUploads(array $sourceArgs, array $destArgs)
    {
        $source = $this->mockInstance($sourceArgs[0]);
        $source->uploadsPath = $sourceArgs[1];

        $dest = $this->mockInstance($destArgs[0]);
        $dest->uploadsPath = $destArgs[1];

        $validCommand = function ($command) use ($source, $dest) {
            if ($source instanceof LocalInstance && $dest instanceof LocalInstance) {
                // Trailing slashes are required on paths for rsync, so it syncs files within the directory rather than the directory itself
                $expect = "rsync -avh --delete \"{$source->uploadsPath}/\" \"{$dest->uploadsPath}/\"";
            } else {
                $expect = "aws s3 sync --delete \"{$source->uploadsPath}\" \"{$dest->uploadsPath}\"";
            }

            return ($command == $expect);
        };

        // testing writeln
        $this->output->expects($this->exactly(2))
            ->method('writeln')
            ->withConsecutive(
                ["Syncing files from <comment>{$source->uploadsPath}</comment> to <comment>{$dest->uploadsPath}</comment>", OutputInterface::VERBOSITY_VERBOSE],
                [$this->stringStartsWith('Running command: '), OutputInterface::VERBOSITY_VERBOSE]
            );

        // testing write
        $this->output->expects($this->exactly(4))
            ->method('write')
            ->withConsecutive(
                ['streamed', false, OutputInterface::VERBOSITY_VERBOSE],
                ['output', false, OutputInterface::VERBOSITY_VERBOSE],
                ['from', false, OutputInterface::VERBOSITY_VERBOSE],
                ['command', false, OutputInterface::VERBOSITY_VERBOSE]
            );

        // mock a process object
        $process = $this->createMock(Process::class);

        // check a symphony Process method will return itself, once
        $process->expects($this->once())->method('disableOutput')->willReturnSelf();

        $process->expects($this->once())
            ->method('mustRun')
            ->with($this->callback('is_callable'))
            ->willReturnCallback(function ($callback) {
                $commandOutput = 'streamed output from command';

                // Split output data into chunks to emulate command output
                // being streamed to the callback via multiple invocations
                foreach (explode(' ', $commandOutput) as $chunk) {
                    $callback(Process::OUT, $chunk);
                }
            });

        $migration = $this->getMockBuilder(Migration::class)
            ->setConstructorArgs([$source, $dest, $this->output])
            ->setMethods(['newProcess', 'getBlogId'])
            ->getMock();

        $migration->expects($this->once())
            ->method('newProcess')
            ->with($this->callback($validCommand))
            ->willReturn($process);

        $migration->syncUploads();

    }

    public function testNewProcess()
    {
        $migration = new Migration($this->mockInstance(), $this->mockInstance(), $this->output);
        $process = $migration->newProcess('command to run');
        $this->assertInstanceOf(Process::class, $process);
        $this->assertEquals('command to run', $process->getCommandLine());
        $this->assertNotNull($process->getTimeout());
    }

    public function testBeginStepNormal()
    {
        $migration = new Migration($this->mockInstance(), $this->mockInstance(), $this->output);

        $this->output->expects($this->any())
            ->method('getVerbosity')
            ->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $this->output->expects($this->once())
            ->method('writeln')
            ->with('Name of step');

        $migration->beginStep('Name of step');
    }

    public function testBeginStepVerbose()
    {
        $migration = new Migration($this->mockInstance(), $this->mockInstance(), $this->output);

        $this->output->expects($this->any())
            ->method('getVerbosity')
            ->willReturn(OutputInterface::VERBOSITY_VERBOSE);

        $terminalWidth = (new Terminal())->getWidth();
        // Match a string of dashes, exactly the width of the current terminal
        // e.g. "------------------------------" for a terminal session 30 columns wide
        $fullLineRegex = sprintf('/^-{%d}$/', $terminalWidth);

        $this->output->expects($this->exactly(3))
            ->method('writeln')
            ->withConsecutive(
                [$this->matchesRegularExpression($fullLineRegex), OutputInterface::VERBOSITY_VERBOSE],
                ['<comment>NAME OF STEP</comment>', OutputInterface::VERBOSITY_VERBOSE],
                [$this->matchesRegularExpression($fullLineRegex), OutputInterface::VERBOSITY_VERBOSE]
            );

        $migration->beginStep('Name of step');
    }

    public function testEndStep()
    {
        $migration = new Migration($this->mockInstance(), $this->mockInstance(), $this->output);

        $this->output->expects($this->once())
            ->method('writeln')
            ->with('', OutputInterface::VERBOSITY_VERBOSE);

        $migration->endStep();
    }

    /**
     * Create a mock Instance object
     *
     * @param string $className Class to mock (default to AbstractInstance)
     * @return MockObject
     */
    protected function mockInstance($className = AbstractInstance::class)
    {
        return $this->getMockForAbstractClass(
            $className,
            [],
            '',
            false,
            false,
            true,
            [
                'env',
                'execute',
                'exportDatabase',
                'importDatabase',
                'syncUploads'
            ]
        );
    }
}
