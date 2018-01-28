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

use KevinGH\Box\Configuration;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use function KevinGH\Box\make_tmp_dir;
use function KevinGH\Box\remove_dir;

/**
 * @covers \KevinGH\Box\Console\ConfigurationHelper
 */
class ConfigurationHelperTest extends TestCase
{
    /**
     * @var ConfigurationHelper
     */
    private $helper;

    /**
     * @var string
     */
    private $cwd;

    /**
     * @var string
     */
    private $tmp;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->cwd = getcwd();

        $this->tmp = make_tmp_dir('box', __CLASS__);

        $this->helper = new ConfigurationHelper();

        chdir($this->tmp);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        chdir($this->cwd);

        remove_dir($this->tmp);
    }

    public function test_schema_constant_is_defined(): void
    {
        $this->assertInternalType('string', BOX_SCHEMA_FILE);
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
        file_put_contents('box.json.dist', '{}');

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
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'The configuration file could not be found.',
                $exception->getMessage()
            );
            $this->assertSame(0, $exception->getCode());
            $this->assertNull($exception->getPrevious());
        }
    }
}
