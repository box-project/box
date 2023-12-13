<?php

declare(strict_types=1);

namespace KevinGH\Box\Composer\Package;

use KevinGH\Box\Composer\Package\Extensions;
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