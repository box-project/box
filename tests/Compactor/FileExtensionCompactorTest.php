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

namespace KevinGH\Box;

use KevinGH\Box\Compactor\DummyFileExtensionCompactor;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Compactor\FileExtensionCompactor
 */
class FileExtensionCompactorTest extends TestCase
{
    public function test_it_supports_the_given_extensions(): void
    {
        $compactor = new DummyFileExtensionCompactor(['php']);

        $this->assertTrue($compactor->supports('test.php'));
        $this->assertFalse($compactor->supports('test'));
    }
}
