<?php

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

    protected function setUp()
    {
        $aws                    = $this->createMock(AwsResources::class);
        $aws->stackName         = 'example-dev';
        $aws->ecsCluster        = 'devcluster';
        $aws->ecsServiceName    = 'example-dev-WebService-XXXXXXXXXXXXX';
        $aws->ecsTaskArn        = 'arn:aws:ecs:eu-west-2:000000000000:task/3caf92c9-9c61-5397-4feb-e7a919225983';
        $aws->ec2Hostname       = 'ec2host.com';
        $aws->dockerContainerId = 'c8a7b8';
        $aws->s3BucketName      = 'example-dev-bucket';

        $this->instance = new AwsInstance('example', 'dev', $aws);
    }

    public function testUploadsPathProperty()
    {
        $this->assertEquals('s3://example-dev-bucket/uploads', $this->instance->uploadsPath);
    }

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
     * @dataProvider commandStringProvider
     */
    public function testNewCommandString($command, $dockerOptions, $sshOptions, $expectedCommandLine)
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
}
