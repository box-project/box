<?php

declare(strict_types=1);

namespace KevinGH\Box\PhpScoper;


use PHPUnit\Framework\TestCase;

class NullScoperTest extends TestCase
{
    public function test_it_returns_the_content_of_the_file_unchanged()
    {
        $file = 'foo';
        $contents = <<<'JSON'
{
    "foo": "bar"
    
}
JSON;

        $scoper = new NullScoper();

        $actual = $scoper->scope($file, $contents);

        $this->assertSame($contents, $actual);
    }

    public function test_it_exposes_some_elements_of_the_scoping_config()
    {
        $scoper = new NullScoper();

        $this->assertSame('', $scoper->getPrefix());
        $this->assertSame([], $scoper->getWhitelist());
    }
}
