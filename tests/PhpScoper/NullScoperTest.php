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

use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use function serialize;
use function unserialize;

/**
 * @internal
 */
#[CoversClass(NullScoper::class)]
class NullScoperTest extends TestCase
{
    private Scoper $scoper;

    protected function setUp(): void
    {
        $this->scoper = new NullScoper();
    }

    public function test_it_returns_the_content_of_the_file_unchanged(): void
    {
        $file = 'foo';
        $contents = <<<'JSON'
            {
                "foo": "bar"

            }
            JSON;

        $actual = $this->scoper->scope($file, $contents);

        self::assertSame($contents, $actual);
    }

    public function test_it_contains_no_prefixes_and_an_empty_symbols_registry(): void
    {
        self::assertSame('', $this->scoper->getPrefix());
        self::assertEquals(new SymbolsRegistry(), $this->scoper->getSymbolsRegistry());
    }

    public function test_it_exposes_the_given_symbols_registry(): void
    {
        $symbolsRegistry = new SymbolsRegistry();

        $scoper = new NullScoper($symbolsRegistry);

        self::assertSame($symbolsRegistry, $scoper->getSymbolsRegistry());
    }

    public function test_it_exposes_the_configured_symbols_registry(): void
    {
        $symbolsRegistry = new SymbolsRegistry();

        $this->scoper->changeSymbolsRegistry($symbolsRegistry);

        self::assertSame($symbolsRegistry, $this->scoper->getSymbolsRegistry());
    }

    public function test_it_is_serializable(): void
    {
        $scoper = new NullScoper();

        self::assertEquals(
            $scoper,
            unserialize(serialize($scoper)),
        );
    }
}
