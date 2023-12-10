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

namespace KevinGH\Box\Compactor;

use KevinGH\Box\UnsupportedMethodCall;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BaseCompactor::class)]
class BaseCompactorTest extends TestCase
{
    public function test_it_returns_the_contents_unchanged_if_does_not_support_the_file(): void
    {
        $file = '/path/to/file';
        $contents = 'file contents';

        $expected = $contents;

        $compactor = new class() extends BaseCompactor {
            protected function compactContent(string $contents): string
            {
                throw UnsupportedMethodCall::forMethod(self::class, __METHOD__);
            }

            protected function supports(string $file): bool
            {
                return false;
            }
        };

        $actual = $compactor->compact($file, $contents);

        self::assertSame($expected, $actual);
    }

    public function test_it_returns_the_compacted_contents_if_it_supports_the_file(): void
    {
        $file = '/path/to/file';
        $contents = 'file contents';

        $expected = 'compacted contents';

        $compactor = new class($expected) extends BaseCompactor {
            public function __construct(private readonly string $expected)
            {
            }

            protected function compactContent(string $contents): string
            {
                return $this->expected;
            }

            protected function supports(string $file): bool
            {
                return true;
            }
        };

        $actual = $compactor->compact($file, $contents);

        self::assertSame($expected, $actual);
    }
}
