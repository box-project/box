<?php

declare(strict_types=1);

/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace KevinGH\Box\PhpScoper;

use Humbug\PhpScoper\Whitelist;
use PHPUnit\Framework\TestCase;
use function serialize;
use function unserialize;

/**
 * @covers \KevinGH\Box\PhpScoper\NullScoper
 */
class NullScoperTest extends TestCase
{
    public function test_it_returns_the_content_of_the_file_unchanged(): void
    {
        $file = 'foo';
        $contents = <<<'JSON'
{
    "foo": "bar"
    
}
JSON;

        $actual = (new NullScoper())->scope($file, $contents);

        $this->assertSame($contents, $actual);
    }

    public function test_it_exposes_some_elements_of_the_scoping_config(): void
    {
        $scoper = new NullScoper();

        $this->assertSame('', $scoper->getPrefix());
        $this->assertEquals(Whitelist::create(true, true, true), $scoper->getWhitelist());
    }

    public function test_it_is_serializable(): void
    {
        $scoper = new NullScoper();

        $this->assertEquals(
            $scoper,
            unserialize(serialize($scoper))
        );
    }
}
