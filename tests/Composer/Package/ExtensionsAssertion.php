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

namespace KevinGH\Box\Composer\Package;

use PHPUnit\Framework\Assert;

final class ExtensionsAssertion
{
    /**
     * @param list<string> $expected
     */
    public static function assertEqual(array $expected, Extensions $actual): void
    {
        $normalizedActual = self::normalizeExtensions($actual);

        Assert::assertSame($expected, $normalizedActual);
    }

    /**
     * @return list<string>
     */
    private static function normalizeExtensions(Extensions $extensions): array
    {
        return array_map(
            strval(...),
            $extensions->extensions,
        );
    }
}
