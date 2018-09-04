<?php

use PHPUnit\Framework\TestCase;
use WpEcs\Wordpress\AbstractInstance;

class AbstractInstanceTest extends TestCase
{
    /**
     * @var AbstractInstance
     */
    protected $instance;

    protected function setUp()
    {
        $instance = $this->getMockForAbstractClass(
            AbstractInstance::class,
            [],
            '',
            false,
            true,
            true,
            ['env']
        );
        $this->instance = $instance;
    }

    public function testEnv()
    {
        $instance = $this->getMockForAbstractClass(
            AbstractInstance::class,
            [],
            '',
            false,
            true,
            true,
            ['execute']
        );

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
}
