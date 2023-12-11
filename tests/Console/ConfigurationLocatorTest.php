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

use Fidry\FileSystem\FS;
use KevinGH\Box\Configuration\NoConfigurationFound;
use KevinGH\Box\Test\FileSystemTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use const DIRECTORY_SEPARATOR;

/**
 * @internal
 */
#[CoversClass(ConfigurationLocator::class)]
class ConfigurationLocatorTest extends FileSystemTestCase
{
    public function test_it_finds_the_default_path(): void
    {
        FS::touch('box.json');

        self::assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'box.json',
            ConfigurationLocator::findDefaultPath(),
        );
    }

    public function test_it_finds_the_default_dist_path(): void
    {
        FS::touch('box.json.dist');

        self::assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'box.json.dist',
            ConfigurationLocator::findDefaultPath(),
        );
    }

    public function test_it_non_dist_file_takes_priority_over_dist_file(): void
    {
        FS::touch('box.json');
        FS::touch('box.json.dist');

        self::assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'box.json',
            ConfigurationLocator::findDefaultPath(),
        );
    }

    public function test_it_throws_an_error_if_no_config_path_is_found(): void
    {
        try {
            ConfigurationLocator::findDefaultPath();

            self::fail('Expected exception to be thrown.');
        } catch (NoConfigurationFound $exception) {
            self::assertSame(
                'The configuration file could not be found.',
                $exception->getMessage(),
            );
            self::assertSame(0, $exception->getCode());
            self::assertNull($exception->getPrevious());
        }
    }
}
