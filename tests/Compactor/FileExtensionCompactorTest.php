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
 * @covers \KevinGH\Box\Compactor\FileExtensionCompactor
 */
class FileExtensionCompactorTest extends TestCase
{
    public function test_it_does_not_support_files_with_unknown_extension(): void
    {
        $file = '/path/to/file.js';
        $contents = 'file contents';

        $expected = $contents;

        $compactor = new class([]) extends FileExtensionCompactor {
            use NotCallable;

            /**
             * {@inheritdoc}
             */
            protected function compactContent(string $contents): string
            {
                $this->__call(__METHOD__, func_get_args());
            }
        };

        $actual = $compactor->compact($file, $contents);

        $this->assertSame($expected, $actual);
    }

    public function test_it_supports_files_with_the_given_extensions(): void
    {
        $file = '/path/to/file.php';
        $contents = 'file contents';

        $expected = 'compacted contents';

        $compactor = new class($expected) extends FileExtensionCompactor {
            private $expected;

            public function __construct(string $expected)
            {
                parent::__construct(['php']);

                $this->expected = $expected;
            }

            /**
             * {@inheritdoc}
             */
            protected function compactContent(string $contents): string
            {
                return $this->expected;
            }
        };

        $actual = $compactor->compact($file, $contents);

        $this->assertSame($expected, $actual);
    }
}
