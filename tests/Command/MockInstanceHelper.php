<?php

namespace WpEcs\Tests\Command;

use WpEcs\Wordpress\AbstractInstance;
use WpEcs\Wordpress\InstanceFactory;

trait MockInstanceHelper {
    /**
     * @var AbstractInstance
     */
    protected $instance;

    /**
     * @var InstanceFactory
     */
    protected $instanceFactory;

    protected function setupMockInstance()
    {
        $this->instance = $this->getMockForAbstractClass(
            AbstractInstance::class,
            [],
            '',
            false,
            true,
            true,
            ['env', 'execute', 'exportDatabase', 'importDatabase']
        );

        $this->instanceFactory = $this->createMock(
            InstanceFactory::class
        );
        $this->instanceFactory->expects($this->any())
                              ->method('create')
                              ->with($this->matchesRegularExpression('/^example:(dev|staging)$/'))
                              ->willReturn($this->instance);
    }
}
