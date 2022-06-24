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

use const DIRECTORY_SEPARATOR;
use KevinGH\Box\Configuration\NoConfigurationFound;
use function KevinGH\Box\FileSystem\touch;
use KevinGH\Box\Test\FileSystemTestCase;

/**
 * @covers \KevinGH\Box\Console\ConfigurationLocator
 */
class ConfigurationLocatorTest extends FileSystemTestCase
{
    public function test_it_finds_the_default_path(): void
    {
        touch('box.json');

        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'box.json',
            ConfigurationLocator::findDefaultPath(),
        );
    }

    public function test_it_finds_the_default_dist_path(): void
    {
        touch('box.json.dist');

        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'box.json.dist',
            ConfigurationLocator::findDefaultPath(),
        );
    }

    public function test_it_non_dist_file_takes_priority_over_dist_file(): void
    {
        touch('box.json');
        touch('box.json.dist');

        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'box.json',
            ConfigurationLocator::findDefaultPath(),
        );
    }

    public function test_it_throws_an_error_if_no_config_path_is_found(): void
    {
        try {
            ConfigurationLocator::findDefaultPath();

            $this->fail('Expected exception to be thrown.');
        } catch (NoConfigurationFound $exception) {
            $this->assertSame(
                'The configuration file could not be found.',
                $exception->getMessage(),
            );
            $this->assertSame(0, $exception->getCode());
            $this->assertNull($exception->getPrevious());
        }
    }
}
