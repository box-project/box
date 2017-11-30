<?php

namespace Herrera\Box\Tests\Compactor;

use Herrera\Box\Compactor\Json;
use Herrera\PHPUnit\TestCase;

class JsonTest extends TestCase
{
    public function testCompact()
    {
        $compactor = new Json();
        $expected = '{"test":123}';
        $original = <<<JSON
{
    "test": 123
}
JSON;

        $this->assertEquals($expected, $compactor->compact($original));
    }
}
