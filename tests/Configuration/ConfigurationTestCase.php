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

namespace KevinGH\Box\Configuration;

use function json_encode;
use const JSON_PRETTY_PRINT;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\make_path_absolute;
use function KevinGH\Box\FileSystem\touch;
use KevinGH\Box\Test\FileSystemTestCase;
use const PHP_OS;
use stdClass;
use function stripos;

abstract class ConfigurationTestCase extends FileSystemTestCase
{
    protected const DEFAULT_FILE = 'index.php';

    /** @var Configuration */
    protected $config;

    /** @var string */
    protected $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = make_path_absolute('box.json', $this->tmp);

        touch(self::DEFAULT_FILE);
        dump_file($this->file, '{}');

        $this->config = Configuration::create($this->file, new stdClass());
    }

    final protected function setConfig(array $config): void
    {
        dump_file($this->file, json_encode($config, JSON_PRETTY_PRINT));

        $this->reloadConfig();
    }

    final protected function reloadConfig(): void
    {
        $this->config = (new ConfigurationLoader())->loadFile($this->file);
    }

    final protected function isWindows(): bool
    {
        return false === stripos(PHP_OS, 'darwin') && false !== stripos(PHP_OS, 'win');
    }

    final protected function getNoFileConfig(): Configuration
    {
        return Configuration::create(null, new stdClass());
    }
}
