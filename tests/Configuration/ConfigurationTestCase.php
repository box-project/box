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

use Fidry\FileSystem\FS;
use KevinGH\Box\Test\FileSystemTestCase;
use stdClass;
use Symfony\Component\Filesystem\Path;
use function json_encode;
use const JSON_PRETTY_PRINT;
use const PHP_OS_FAMILY;

abstract class ConfigurationTestCase extends FileSystemTestCase
{
    protected const DEFAULT_FILE = 'index.php';

    protected Configuration $config;
    protected string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = Path::makeAbsolute('box.json', $this->tmp);

        FS::touch(self::DEFAULT_FILE);
        FS::dumpFile($this->file, '{}');

        $this->config = Configuration::create($this->file, new stdClass());
    }

    final protected function setConfig(array $config): void
    {
        FS::dumpFile($this->file, json_encode($config, JSON_PRETTY_PRINT));

        $this->reloadConfig();
    }

    final protected function reloadConfig(): void
    {
        $this->config = (new ConfigurationLoader())->loadFile($this->file);
    }

    final protected function isWindows(): bool
    {
        return 'Windows' === PHP_OS_FAMILY;
    }

    final protected function getNoFileConfig(): Configuration
    {
        return Configuration::create(null, new stdClass());
    }
}
