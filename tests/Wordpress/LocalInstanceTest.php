<?php

namespace WpEcs\Tests\Wordpress;

use WpEcs\Wordpress\LocalInstance;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use org\bovigo\vfs\vfsStream;

class LocalInstanceTest extends TestCase
{
    public function testName()
    {
        $instance = new LocalInstance('/path/to/local-instance');
        $this->assertEquals('local-instance', $instance->name);
    }

    public function testDockerContainerId()
    {
        $containerId = 'c8a7b8';

        $process = $this->createMock(Process::class);
        $process->expects($this->once())
                ->method('mustRun')
                ->willReturnSelf();
        $process->expects($this->once())
                ->method('getOutput')
                ->willReturn("$containerId\n");

        $instance = $this->createPartialMock(
            LocalInstance::class,
            ['newProcess']
        );

        $instance->expects($this->once())
                 ->method('newProcess')
                 ->with('docker-compose ps -q wordpress')
                 ->willReturn($process);

        for ($i = 0; $i < 3; $i++) {
            // The first call should execute the `docker-compose` command and cache the response
            // Subsequent calls should use the cache, so $process is only ever run once
            $actual = $instance->dockerContainerId;
            $this->assertEquals($containerId, $actual);
        }
    }

    public function testUploadsBaseUrl()
    {
        $wpHome = 'http://local-instance.docker';

        $instance = $this->createPartialMock(
            LocalInstance::class,
            ['env']
        );

        $instance->expects($this->once())
                 ->method('env')
                 ->with('WP_HOME')
                 ->willReturn($wpHome);

        $expected = "$wpHome/app/uploads";
        $actual = $instance->uploadsBaseUrl;
        $this->assertEquals($expected, $actual);
    }

    public function testUploadsPath()
    {
        $instance = new LocalInstance('/path/to/local-instance');
        $expected = '/path/to/local-instance/web/app/uploads';
        $this->assertEquals($expected, $instance->uploadsPath);
    }

    /**
     * Data Provider
     * Supply commands as a STRING
     *
     * @return array
     */
    public function commandStringProvider()
    {
        return [
            [
                // Simple command
                'ls',
                [],
                "'docker' 'exec' 'c8a7b8' 'ls'",
            ],
            [
                // Command with parameter containing spaces & option flags (which must be passed, escaped, to the docker exec command)
                // Parameters wrapped in quotes must be executed as one parameter
                'echo -n "Hello world"',
                [],
                "'docker' 'exec' 'c8a7b8' 'echo' '-n' 'Hello world'",
            ],
            [
                // Command with docker and SSH options
                'bash',
                ['-ti'],
                "'docker' 'exec' '-ti' 'c8a7b8' 'bash'",
            ]
        ];
    }

    /**
     * Data Provider
     * Supply commands as an ARRAY
     *
     * @return array
     */
    public function commandArrayProvider()
    {
        return [
            [
                // Simple command
                ['ls'],
                [],
                "'docker' 'exec' 'c8a7b8' 'ls'",
            ],
            [
                // Command with parameter containing spaces & option flags (which must be passed, escaped, to the docker exec command)
                [
                    'echo',
                    '-n',
                    'Hello world'
                ],
                [],
                "'docker' 'exec' 'c8a7b8' 'echo' '-n' 'Hello world'",
            ],
            [
                // Command with docker and SSH options
                ['bash'],
                ['-ti'],
                "'docker' 'exec' '-ti' 'c8a7b8' 'bash'",
            ]
        ];
    }

    /**
     * @dataProvider commandStringProvider
     * @dataProvider commandArrayProvider
     * @param string|array $command
     * @param array $dockerOptions
     * @param string $expectedCommandLine
     */
    public function testNewCommand($command, $dockerOptions, $expectedCommandLine)
    {
        $vfs = vfsStream::setup('root', null, [
            'path/to/local-instance' => [
                'docker-compose.yml' => ''
            ]
        ]);
        $workingDirectory = "{$vfs->url()}/path/to/local-instance";

        $instance = $this->getMockBuilder(LocalInstance::class)
                         ->setConstructorArgs([$workingDirectory])
                         ->setMethods(['getDockerContainerId'])
                         ->getMock();

        $instance->expects($this->once())
                 ->method('getDockerContainerId')
                 ->willReturn('c8a7b8');

        $process = $instance->newCommand($command, $dockerOptions);

        $this->assertInstanceOf(Process::class, $process);
        $this->assertEquals($workingDirectory, $process->getWorkingDirectory());
        $this->assertEquals($expectedCommandLine, $process->getCommandLine());
    }

    public function runningStatusProvider()
    {
        return [
            // [ output from docker-compose, running? ]
            ['c8a7b8', true ],
            ['',       false],
        ];
    }

    /**
     * @param string $mockOutput Output from docker-compose command
     * @param bool $expectRunning Expected suject return value
     *
     * @dataProvider runningStatusProvider
     */
    public function testIsRunning($mockOutput, $expectRunning)
    {
        $process = $this->createMock(Process::class);
        $process->expects($this->once())
                ->method('mustRun')
                ->willReturnSelf();
        $process->expects($this->once())
                ->method('getOutput')
                ->willReturn("$mockOutput\n");

        $instance = $this->createPartialMock(
            LocalInstance::class,
            ['newProcess']
        );

        $instance->expects($this->once())
                 ->method('newProcess')
                 ->with('docker-compose ps -q wordpress')
                 ->willReturn($process);

        $this->assertEquals($expectRunning, $instance->isRunning());
    }
}
