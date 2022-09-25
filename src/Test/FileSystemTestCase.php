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

namespace KevinGH\Box\Test;

use function array_map;
use function array_values;
use function chdir;
use const DIRECTORY_SEPARATOR;
use function getcwd;
use function KevinGH\Box\FileSystem\make_tmp_dir;
use function KevinGH\Box\FileSystem\remove;
use function natcasesort;
use PHPUnit\Framework\TestCase;
use function realpath;
use function str_replace;
use function sys_get_temp_dir;

/**
 * @private
 */
abstract class FileSystemTestCase extends TestCase
{
    /** @var string */
    protected $cwd;

    /** @var string */
    protected $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        // Cleans up whatever was there before. Indeed upon failure PHPUnit fails to trigger the `tearDown()` method
        // and as a result some temporary files may still remain.
        remove(str_replace('\\', '/', realpath(sys_get_temp_dir())).'/box');

        $this->cwd = getcwd();
        $this->tmp = make_tmp_dir('box', self::class);

        chdir($this->tmp);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        chdir($this->cwd);

        remove($this->tmp);
    }

    /**
     * @param string[] $files
     *
     * @return string[] File real paths relative to the current temporary directory
     */
    final protected function normalizePaths(array $files): array
    {
        $root = $this->tmp;

        $files = array_values(
            array_map(
                static fn (string $file): string => str_replace($root.DIRECTORY_SEPARATOR, '', $file),
                $files,
            ),
        );

        natsort($files);
        natcasesort($files);

        return array_values($files);
    }
}
