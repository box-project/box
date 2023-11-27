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

namespace KevinGH\Box\Console;

use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Console\DisplayNormalizer
 * @internal
 */
final class DisplayNormalizerTest extends TestCase
{
    /**
     * @dataProvider blockProvider
     */
    public static function test_it_can_remove_the_line_returns_from_blocks(
        string $value,
        ?string $expected,
    ): void {
        $expected ??= $value;

        $actual = DisplayNormalizer::removeBlockLineReturn($value);

        self::assertSame($expected, $actual);
    }

    public static function blockProvider(): iterable
    {
        $unchanged = null;

        yield 'no line return' => [
            <<<'EOF'

                 [ERROR] Error message.


                EOF,
            $unchanged,
        ];

        yield 'one line return' => [
            <<<'EOF'

                 [ERROR] Error message...
                         ...and the other piece here.


                EOF,
            <<<'EOF'

                 [ERROR] Error message......and the other piece here.


                EOF,
        ];

        yield 'two line return' => [
            <<<'EOF'

                 [ERROR] Error message...
                         ...and the other piece here.
                          And one more.


                EOF,
            <<<'EOF'

                 [ERROR] Error message......and the other piece here. And one more.


                EOF,
        ];
    }
}
