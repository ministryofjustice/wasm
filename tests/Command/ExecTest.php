<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use WpEcs\Command\Exec;

class ExecTest extends TestCase
{
    /**
     * @var Application
     */
    protected $application;

    public function setUp()
    {
        $this->application = new Application();
        $this->application->add(new Exec());
    }

    public function testConfigure()
    {
        $command = $this->application->find('exec');
        $this->assertInstanceOf(Exec::class, $command);
    }
}
