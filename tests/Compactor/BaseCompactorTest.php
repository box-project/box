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

use function func_get_args;
use KevinGH\Box\NotCallable;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Compactor\BaseCompactor
 */
class BaseCompactorTest extends TestCase
{
    public function test_it_returns_the_contents_unchanged_if_does_not_support_the_file(): void
    {
        $file = '/path/to/file';
        $contents = 'file contents';

        $expected = $contents;

        $compactor = new class() extends BaseCompactor {
            use NotCallable;

            /**
             * {@inheritdoc}
             */
            protected function compactContent(string $contents): string
            {
                $this->__call(__METHOD__, func_get_args());
            }

            /**
             * {@inheritdoc}
             */
            protected function supports(string $file): bool
            {
                return false;
            }
        };

        $actual = $compactor->compact($file, $contents);

        $this->assertSame($expected, $actual);
    }

    public function test_it_returns_the_compacted_contents_if_it_supports_the_file(): void
    {
        $file = '/path/to/file';
        $contents = 'file contents';

        $expected = 'compacted contents';

        $compactor = new class($expected) extends BaseCompactor {
            private $expected;

            public function __construct(string $expected)
            {
                $this->expected = $expected;
            }

            /**
             * {@inheritdoc}
             */
            protected function compactContent(string $contents): string
            {
                return $this->expected;
            }

            /**
             * {@inheritdoc}
             */
            protected function supports(string $file): bool
            {
                return true;
            }
        };

        $actual = $compactor->compact($file, $contents);

        $this->assertSame($expected, $actual);
    }
}
