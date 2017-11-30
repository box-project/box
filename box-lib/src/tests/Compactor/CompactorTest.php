<?php

namespace Herrera\Box\Tests\Compactor;

use Herrera\PHPUnit\TestCase;

class CompactorTest extends TestCase
{
    /**
     * @var BaseCompactor
     */
    private $compactor;

    public function testSetExtensions()
    {
        $this->compactor->setExtensions(array('php'));

        $this->assertTrue($this->compactor->supports('test.php'));
        $this->assertFalse($this->compactor->supports('test'));

    }

    protected function setUp()
    {
        $this->compactor = new BaseCompactor();
    }
}
