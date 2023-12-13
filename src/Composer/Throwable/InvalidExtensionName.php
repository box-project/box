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

namespace KevinGH\Box\Composer\Throwable;

use RuntimeException;
use Throwable;
use function sprintf;

final class InvalidExtensionName extends RuntimeException
{
    public static function forName(string $name, ?Throwable $previous = null): self
    {
        return new self(
            sprintf(
                'The name "%s" is not a valid PHP extension name.',
                $name,
            ),
            previous: $previous,
        );
    }

    public static function forPolyfillPackage(string $name, ?Throwable $previous = null): self
    {
        return new self(
            sprintf(
                'The name "%s" is not a valid PHP extension polyfill package name.',
                $name,
            ),
            previous: $previous,
        );
    }
}
