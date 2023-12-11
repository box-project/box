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

use ErrorException;
use Isolated\Symfony\Component\Finder\Finder as IsolatedFinder;
use Symfony\Component\Finder\Finder as SymfonyFinder;
use function class_alias;
use function class_exists;
use function error_reporting;
use function set_error_handler;

final class Bootstrap
{
    use NotInstantiable;

    /**
     * @private
     */
    public static function registerAliases(): void
    {
        // Exposes the finder used by PHP-Scoper PHAR to allow its usage in the configuration file.
        if (false === class_exists(IsolatedFinder::class)) {
            class_alias(SymfonyFinder::class, IsolatedFinder::class);
        }
    }

    public static function registerErrorHandler(): void
    {
        set_error_handler(
            static function (int $code, string $message, string $file = '', int $line = -1): void {
                if (error_reporting() & $code) {
                    throw new ErrorException($message, 0, $code, $file, $line);
                }
            },
        );
    }
}
