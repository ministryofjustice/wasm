<?php

use PHPUnit\Framework\TestCase;
use WpEcs\Traits\LazyPropertiesTrait;

class LazyPropertiesTraitTest extends TestCase
{
    /**
     * @var LazyPropertiesTrait
     */
    protected $subject;

    protected function setUp()
    {
        $this->subject = $this->getMockForTrait(
            LazyPropertiesTrait::class,
            [],
            '',
            true,
            true,
            true,
            ['getMyLazyProperty']
        );
    }

    public function testLazyGetProperty()
    {
        $expected = "Ohh, I'm wicked and I'm lazy";

        $this->subject->expects($this->once())
                      ->method('getMyLazyProperty')
                      ->willReturn($expected);

        for ($i = 0; $i < 3; $i++) {
            // Method should only be executed on first call to the property
            // Subsequent calls should return from the cache
            $actual = $this->subject->myLazyProperty;
            $this->assertEquals($expected, $actual);
        }
    }

    public function testLazyGetNonExistentProperty()
    {
        $actual = $this->subject->someOtherProperty;
        $this->assertNull($actual);
    }

    public function testDoNotLazyGetPropertiesAlreadyDefined()
    {
        $value = 'Concrete property, not lazy';
        $this->subject->myLazyProperty = $value;

        $this->subject->expects($this->never())
                      ->method('getMyLazyProperty');

        $this->assertEquals($value, $this->subject->myLazyProperty);
    }
}
