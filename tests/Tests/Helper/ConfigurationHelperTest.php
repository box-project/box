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

namespace KevinGH\Box\Tests;

use Herrera\PHPUnit\TestCase;
use KevinGH\Box\Helper\ConfigurationHelper;
use RuntimeException;

/**
 * @coversNothing
 */
class ConfigurationHelperTest extends TestCase
{
    /**
     * @var ConfigurationHelper
     */
    private $helper;

    private $cwd;
    private $dir;

    protected function setUp(): void
    {
        $this->cwd = getcwd();
        $this->dir = $this->createDir();
        $this->helper = new ConfigurationHelper();

        chdir($this->dir);
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
    }

    public function testConstant(): void
    {
        $this->assertInternalType('string', BOX_SCHEMA_FILE);
    }

    public function testGetName(): void
    {
        $this->assertSame('config', $this->helper->getName());
    }

    public function testGetDefaultPath(): void
    {
        touch('box.json');

        $this->assertSame(
            $this->dir.DIRECTORY_SEPARATOR.'box.json',
            $this->helper->getDefaultPath()
        );
    }

    public function testGetDefaultPathDist(): void
    {
        touch('box.json.dist');

        $this->assertSame(
            $this->dir.DIRECTORY_SEPARATOR.'box.json.dist',
            $this->helper->getDefaultPath()
        );
    }

    public function testLoadFile(): void
    {
        file_put_contents('box.json.dist', '{}');

        $this->assertInstanceOf(
            'KevinGH\\Box\\Configuration',
            $this->helper->loadFile()
        );
    }

    public function testLoadFileImport(): void
    {
        file_put_contents('box.json', '{"import": "test.json"}');
        file_put_contents('test.json', '{"alias": "import.phar"}');

        $config = $this->helper->loadFile();

        $this->assertSame(
            'import.phar',
            $config->getAlias()
        );
    }

    public function testGetDefaultPathNotExist(): void
    {
        try {
            $this->helper->getDefaultPath();

            $this->fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'The configuration file could not be found.',
                $exception->getMessage()
            );
        }
    }
}
