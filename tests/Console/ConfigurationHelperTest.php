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
use KevinGH\Box\Configuration\Configuration;
use KevinGH\Box\Configuration\NoConfigurationFound;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\touch;
use KevinGH\Box\Test\FileSystemTestCase;

/**
 * @covers \KevinGH\Box\Console\ConfigurationHelper
 */
class ConfigurationHelperTest extends FileSystemTestCase
{
    /** @var ConfigurationHelper */
    private $helper;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->helper = new ConfigurationHelper();
    }

    public function test_it_has_a_name(): void
    {
        $this->assertSame('config', $this->helper->getName());
    }

    public function test_it_finds_the_default_path(): void
    {
        touch('box.json');

        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'box.json',
            $this->helper->findDefaultPath()
        );
    }

    public function test_it_finds_the_default_dist_path(): void
    {
        touch('box.json.dist');

        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'box.json.dist',
            $this->helper->findDefaultPath()
        );
    }

    public function test_it_non_dist_file_takes_priority_over_dist_file(): void
    {
        touch('box.json');
        touch('box.json.dist');

        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'box.json',
            $this->helper->findDefaultPath()
        );
    }

    public function test_it_can_load_a_configuration(): void
    {
        touch('index.php');
        dump_file('box.json.dist', '{}');

        $this->assertInstanceOf(
            Configuration::class,
            $this->helper->loadFile(
                $this->helper->findDefaultPath()
            )
        );
    }

    public function test_it_can_load_a_configuration_without_a_file(): void
    {
        touch('index.php');

        $this->assertInstanceOf(
            Configuration::class,
            $this->helper->loadFile(null)
        );
    }

    public function test_it_throws_an_error_if_no_config_path_is_found(): void
    {
        try {
            $this->helper->findDefaultPath();

            $this->fail('Expected exception to be thrown.');
        } catch (NoConfigurationFound $exception) {
            $this->assertSame(
                'The configuration file could not be found.',
                $exception->getMessage()
            );
            $this->assertSame(0, $exception->getCode());
            $this->assertNull($exception->getPrevious());
        }
    }
}
