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

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @covers \KevinGH\Box\PhpScoper\SimpleScoper
 */
class SimpleScoperTest extends TestCase
{
    use ProphecyTrait;

    public function test_todo(): void
    {
        $this->markTestSkipped('TODO: need rework');
    }
}
