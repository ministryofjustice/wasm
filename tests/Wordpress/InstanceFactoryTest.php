<?php

use WpEcs\Wordpress\AwsInstance;
use WpEcs\Wordpress\LocalInstance;
use WpEcs\Wordpress\InstanceFactory;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;

class InstanceFactoryTest extends TestCase
{
    /**
     * @var InstanceFactory
     */
    protected $subject;

    protected function setUp()
    {
        $this->subject = new InstanceFactory();
    }

    public function testCreateWithInvalidIdentifier()
    {
        $this->expectException(Exception::class);
        $this->subject->create('invalid identifier');
    }

    public function awsIdentifierProvider()
    {
        return [
            ['example:dev'],
            ['example:staging'],
            ['example:prod'],
            ['example2:dev'], // Identifier containing a number
            ['app-name:dev'], // Identifier containing a dash
        ];
    }

    /**
     * @dataProvider awsIdentifierProvider
     */
    public function testCreateWithAwsIdentifier($identifier)
    {
        $instance = $this->subject->create($identifier);
        $this->assertInstanceOf(AwsInstance::class, $instance);
    }

    public function testCreateWithInvalidAwsIdentifier()
    {
        $this->expectException(Exception::class);
        $this->subject->create('example:qa');
    }

    public function localFilenameProvider()
    {
        return [
            ['docker-compose.yml'],
            ['docker-compose.yaml'],
        ];
    }

    /**
     * @dataProvider localFilenameProvider
     */
    public function testCreateWithLocalIdentifier($filename)
    {
        $structure = [$filename => ''];
        $vfs = vfsStream::setup('root', null, $structure);

        $instance = $this->subject->create($vfs->url());
        $this->assertInstanceOf(LocalInstance::class, $instance);
    }

    public function testCreateWithEmptyLocalDirectory()
    {
        $vfs = vfsStream::setup();

        $this->expectException(Exception::class);
        $this->subject->create($vfs->url());
    }
}
