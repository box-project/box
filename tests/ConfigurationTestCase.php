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

use KevinGH\Box\Console\ConfigurationHelper;
use KevinGH\Box\Test\FileSystemTestCase;
use stdClass;
use const DIRECTORY_SEPARATOR;
use function file_put_contents;
use function KevinGH\Box\FileSystem\make_path_absolute;
use function natcasesort;

abstract class ConfigurationTestCase extends FileSystemTestCase
{
    protected const DEFAULT_FILE = 'index.php';

    /**
     * @var Configuration
     */
    protected $config;

    /**
     * @var string
     */
    protected $file;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->file = make_path_absolute('box.json', $this->tmp);

        touch($defaultFile = self::DEFAULT_FILE);
        file_put_contents($this->file, '{}');

        $this->config = Configuration::create($this->file, new stdClass());
    }

    final protected function setConfig(array $config): void
    {
        file_put_contents($this->file, json_encode($config, JSON_PRETTY_PRINT));

        $this->reloadConfig();
    }

    final protected function reloadConfig(): void
    {
        $configHelper = new ConfigurationHelper();

        $this->config = $configHelper->loadFile($this->file);
    }

    final protected function isWindows(): bool
    {
        return false === strpos(strtolower(PHP_OS), 'darwin') && false !== strpos(strtolower(PHP_OS), 'win');
    }

    /**
     * @param string[] $files
     *
     * @return string[] File real paths relative to the current temporary directory
     */
    final protected function normalizeConfigPaths(array $files): array
    {
        $root = $this->tmp;

        $files = array_values(
            array_map(
                function (string $file) use ($root): string {
                    return str_replace($root.DIRECTORY_SEPARATOR, '', $file);
                },
                $files
            )
        );

        natcasesort($files);

        return array_values($files);
    }

    final protected function getNoFileConfig(): Configuration
    {
        return Configuration::create(null, new stdClass());
    }
}
