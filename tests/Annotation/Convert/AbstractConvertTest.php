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

namespace KevinGH\Box\Annotation\Convert;

use Doctrine\Common\Annotations\DocLexer;
use KevinGH\Box\Annotation\TestConvert;
use KevinGH\Box\Annotation\Tokens;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Annotation\Convert\AbstractConvert
 */
class AbstractConvertTest extends TestCase
{
    public function testConvert(): void
    {
        $converter = new TestConvert();
        $tokens = new Tokens(
            [
                [DocLexer::T_AT],
                [DocLexer::T_AT],
                [DocLexer::T_AT],
                [DocLexer::T_AT],
            ]
        );

        $result = $converter->convert($tokens);

        $this->assertEquals(4, $result);
        $this->assertSame($tokens, $converter->tokens);
    }
}
