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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ExcludedFilesScoper::class)]
final class ExcludedFilesScoperTest extends TestCase
{
    private CallRecorderScoper $decoratedScoper;
    private ExcludedFilesScoper $scoper;

    protected function setUp(): void
    {
        $this->decoratedScoper = new CallRecorderScoper();

        $this->scoper = new ExcludedFilesScoper(
            $this->decoratedScoper,
            'fileA',
            'fileB',
        );
    }

    public function test_it_returns_content_unchanged_if_file_is_excluded(): void
    {
        self::assertSame('content', $this->scoper->scope('fileA', 'content'));
        self::assertSame('content', $this->scoper->scope('fileB', 'content'));
    }

    public function test_it_uses_the_decorated_scoper_for_non_excluded_files(): void
    {
        $expectedOutput = 'content';
        $expectedCalls = [['fileC', 'content']];

        $output = $this->scoper->scope('fileC', 'content');
        $actualCalls = $this->decoratedScoper->getCalls();

        self::assertSame($expectedOutput, $output);
        self::assertSame($expectedCalls, $actualCalls);
    }
}
