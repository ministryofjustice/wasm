<?php

namespace WpEcs\Tests\Wordpress;

use WpEcs\Wordpress\AwsInstance;
use WpEcs\Wordpress\AwsInstance\AwsResources;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class AwsInstanceTest extends TestCase
{
    /**
     * @var AwsInstance
     */
    protected $instance;

    protected function setUp(): void
    {
        $aws = $this->createMock(AwsResources::class);
        $aws->stackName = 'example-dev';
        $aws->ecsCluster = 'devcluster';
        $aws->ecsServiceName = 'example-dev-WebService-XXXXXXXXXXXXX';
        $aws->ecsTaskArn = 'arn:aws:ecs:eu-west-2:000000000000:task/3caf92c9-9c61-5397-4feb-e7a919225983';
        $aws->ec2Hostname = 'ec2host.com';
        $aws->dockerContainerId = 'c8a7b8';
        $aws->s3BucketName = 'example-dev-bucket';

        $this->instance = new AwsInstance('example', 'dev', $aws);
    }

    public function testUploadsPathProperty()
    {
        $this->assertEquals('s3://example-dev-bucket/uploads', $this->instance->uploadsPath);
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
                [],
                "'ssh' 'ec2-user@ec2host.com' 'docker' 'exec' 'c8a7b8' ''\''ls'\'''",
            ],
            [
                // Command with parameter containing spaces & option flags (which must be passed, escaped, to the docker exec command)
                // Parameters wrapped in quotes must be executed as one parameter
                'echo -n "Hello world"',
                [],
                [],
                "'ssh' 'ec2-user@ec2host.com' 'docker' 'exec' 'c8a7b8' ''\''echo'\''' ''\''-n'\''' ''\''Hello world'\'''",
            ],
            [
                // Command with docker and SSH options
                'bash',
                ['-ti'],
                ['-t'],
                "'ssh' 'ec2-user@ec2host.com' '-t' 'docker' 'exec' '-ti' 'c8a7b8' ''\''bash'\'''",
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
                [],
                "'ssh' 'ec2-user@ec2host.com' 'docker' 'exec' 'c8a7b8' ''\''ls'\'''",
            ],
            [
                // Command with parameter containing spaces & option flags (which must be passed, escaped, to the docker exec command)
                [
                    'echo',
                    '-n',
                    'Hello world'
                ],
                [],
                [],
                "'ssh' 'ec2-user@ec2host.com' 'docker' 'exec' 'c8a7b8' ''\''echo'\''' ''\''-n'\''' ''\''Hello world'\'''",
            ],
            [
                // Command with docker and SSH options
                ['bash'],
                ['-ti'],
                ['-t'],
                "'ssh' 'ec2-user@ec2host.com' '-t' 'docker' 'exec' '-ti' 'c8a7b8' ''\''bash'\'''",
            ]
        ];
    }

    /**
     * @dataProvider commandStringProvider
     * @dataProvider commandArrayProvider
     * @param string|array $command
     * @param array $dockerOptions
     * @param array $sshOptions
     * @param string $expectedCommandLine
     */
    public function testNewCommand($command, $dockerOptions, $sshOptions, $expectedCommandLine)
    {
        if ($sshOptions == []) {
            // Don't pass optional parameter $sshOptions if it's empty
            $process = $this->instance->newCommand($command, $dockerOptions);
        } else {
            $process = $this->instance->newCommand($command, $dockerOptions, $sshOptions);
        }
        $this->assertInstanceOf(Process::class, $process);
        $this->assertEquals($expectedCommandLine, $process->getCommandLine());
    }

    public function testName()
    {
        $name = $this->instance->name;
        $this->assertEquals('example-dev', $name);
    }

    public function testUploadsBaseUrl()
    {
        $instance = $this->createPartialMock(
            AwsInstance::class,
            ['env']
        );

        $expected = 'https://s3-eu-west-2.amazonaws.com/example-dev-bucket/uploads';

        $instance->expects($this->once())
            ->method('env')
            ->with('S3_UPLOADS_BASE_URL')
            ->willReturn($expected);

        $actual = $instance->uploadsBaseUrl;
        $this->assertEquals($expected, $actual);
    }
}
