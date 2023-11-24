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

use KevinGH\Box\Configuration\NoConfigurationFound;
use KevinGH\Box\NotInstantiable;
use function file_exists;
use function realpath;

/**
 * @private
 */
final class ConfigurationLocator
{
    use NotInstantiable;

    private const FILE_NAME = 'box.json';

    /**
     * @var list<non-empty-string>
     */
    private static array $candidates;

    public static function findDefaultPath(): string
    {
        if (!isset(self::$candidates)) {
            self::$candidates = [
                self::FILE_NAME,
                self::FILE_NAME.'.dist',
            ];
        }

        foreach (self::$candidates as $candidate) {
            if (file_exists($candidate)) {
                return realpath($candidate);
            }
        }

        throw new NoConfigurationFound();
    }
}
