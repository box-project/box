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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DisplayNormalizer::class)]
final class DisplayNormalizerTest extends TestCase
{
    #[DataProvider('blockProvider')]
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

                 [ERROR] Error message... ...and the other piece here.


                EOF,
        ];

        yield 'two line return' => [
            <<<'EOF'

                 [ERROR] Error message...
                         ...and the other piece here.
                          And one more.


                EOF,
            <<<'EOF'

                 [ERROR] Error message... ...and the other piece here.  And one more.


                EOF,
        ];
    }

    #[DataProvider('varDumperObjectReferenceProvider')]
    public static function test_it_can_normalize_an_object_reference_from_the_var_dumper(
        string $value,
        ?string $expected,
    ): void {
        $expected ??= $value;

        $actual = DisplayNormalizer::normalizeVarDumperObjectReference($value);

        self::assertSame($expected, $actual);
    }

    public static function varDumperObjectReferenceProvider(): iterable
    {
        $unchanged = null;

        yield '3 digits' => [
            <<<'EOF'
                Humbug\\PhpScoper\\Symbol\\SymbolsRegistry {#500
                  -recordedFunctions: []
                  -recordedClasses: []
                }

                EOF,
            <<<'EOF'
                Humbug\\PhpScoper\\Symbol\\SymbolsRegistry {#140
                  -recordedFunctions: []
                  -recordedClasses: []
                }

                EOF,
        ];

        yield '2 digits' => [
            <<<'EOF'
                Humbug\\PhpScoper\\Symbol\\SymbolsRegistry {#50
                  -recordedFunctions: []
                  -recordedClasses: []
                }

                EOF,
            <<<'EOF'
                Humbug\\PhpScoper\\Symbol\\SymbolsRegistry {#140
                  -recordedFunctions: []
                  -recordedClasses: []
                }

                EOF,
        ];

        yield '1 digit' => [
            <<<'EOF'
                Humbug\\PhpScoper\\Symbol\\SymbolsRegistry {#5
                  -recordedFunctions: []
                  -recordedClasses: []
                }

                EOF,
            <<<'EOF'
                Humbug\\PhpScoper\\Symbol\\SymbolsRegistry {#140
                  -recordedFunctions: []
                  -recordedClasses: []
                }

                EOF,
        ];
    }
}
