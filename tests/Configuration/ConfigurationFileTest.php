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
use InvalidArgumentException;
use KevinGH\Box\Json\JsonValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Path;
use function chdir;
use function file_get_contents;
use function Safe\json_decode;
use function sprintf;
use const DIRECTORY_SEPARATOR;

/**
 * @internal
 */
#[CoversClass(Configuration::class)]
#[Group('config')]
class ConfigurationFileTest extends ConfigurationTestCase
{
    private const FIXTURES_DIR = __DIR__.'/../../fixtures/configuration';

    public function test_the_files_can_be_configured(): void
    {
        FS::touch('file0');
        FS::touch('file1');

        FS::mkdir('B');
        FS::touch('B/file1');
        FS::touch('B/fileB0');
        FS::touch('B/fileB1');
        FS::touch('B/glob_finder_excluded_file');
        FS::touch('B/glob-finder_excluded_file');

        FS::mkdir('C');
        FS::touch('C/fileC0');
        FS::touch('C/fileC1');

        FS::mkdir('D');
        FS::touch('D/fileD0');
        FS::touch('D/fileD1');
        FS::touch('D/finder_excluded_file');

        FS::mkdir('E');
        FS::touch('E/fileE0');
        FS::touch('E/fileE1');
        FS::touch('E/finder_excluded_file');

        FS::mkdir('F');
        FS::touch('F/fileF0');
        FS::touch('F/fileF1');
        FS::touch('F/fileF2');
        FS::touch('F/fileF3');

        FS::mkdir('vendor');
        FS::touch('vendor/glob_finder_excluded_file');
        FS::touch('vendor/glob-finder_excluded_file');

        FS::mkdir('vendor-bin');
        FS::touch('vendor-bin/file1');

        $this->setConfig([
            'files' => [
                'file0',
                'file1',
            ],
            'directories' => [
                'B',
                'C',
            ],
            'finder' => [
                [
                    'in' => [
                        'D',
                    ],
                    'name' => 'fileD*',
                    'append' => [
                        'F/fileF0',
                        'F/fileF1',
                    ],
                ],
                [
                    'in' => [
                        'E',
                    ],
                    'name' => 'fileE*',
                    'append' => [
                        'F/fileF2',
                        'F/fileF3',
                    ],
                ],
            ],
            'blacklist' => [
                'file1',
                'B/fileB1',
                'C/fileC1',
                'D/fileD1',
                'E/fileE1',
                'glob_finder_excluded_file',
                'glob-finder_excluded_file',
                'vendor-bin',
            ],
        ]);

        // Relative to the current working directory for readability
        $expected = [
            'B/fileB0',
            'C/fileC0',
            'D/fileD0',
            'E/fileE0',
            'F/fileF0',
            'F/fileF1',
            'F/fileF2',
            'F/fileF3',
            'file0',
            'file1',    // 'files' & 'files-bin' are not affected by the blacklist filter
        ];

        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertSame($expected, $actual);
        self::assertCount(0, $this->config->getBinaryFiles());
    }

    #[DataProvider('configWithMainScriptProvider')]
    public function test_the_main_script_file_is_always_ignored(callable $setUp, array $config, array $expectedFiles, array $expectedBinFiles): void
    {
        $setUp();

        $this->setConfig($config);

        $actualFiles = $this->normalizePaths($this->config->getFiles());
        $actualBinFiles = $this->normalizePaths($this->config->getBinaryFiles());

        self::assertSame($expectedFiles, $actualFiles);
        self::assertSame($expectedBinFiles, $actualBinFiles);
    }

    #[DataProvider('configWithGeneratedArtefactProvider')]
    public function test_the_generated_artefact_is_always_ignored(callable $setUp, array $config, array $expectedFiles, array $expectedBinFiles): void
    {
        $setUp();

        $this->setConfig($config);

        $actualFiles = $this->normalizePaths($this->config->getFiles());
        $actualBinFiles = $this->normalizePaths($this->config->getBinaryFiles());

        self::assertSame($expectedFiles, $actualFiles);
        self::assertSame($expectedBinFiles, $actualBinFiles);
    }

    public function test_configured_files_are_relative_to_base_path(): void
    {
        FS::mkdir('sub-dir');

        FS::rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        chdir('sub-dir');

        FS::touch('file0');
        FS::touch('file1');

        FS::mkdir('B');
        FS::touch('B/fileB0');
        FS::touch('B/fileB1');
        FS::touch('B/glob_finder_excluded_file');
        FS::touch('B/glob-finder_excluded_file');

        FS::mkdir('C');
        FS::touch('C/fileC0');
        FS::touch('C/fileC1');

        FS::mkdir('D');
        FS::touch('D/fileD0');
        FS::touch('D/fileD1');
        FS::touch('D/finder_excluded_file');

        FS::mkdir('E');
        FS::touch('E/fileE0');
        FS::touch('E/fileE1');
        FS::touch('E/finder_excluded_file');

        FS::mkdir('vendor');
        FS::touch('vendor/glob_finder_excluded_file');
        FS::touch('vendor/glob-finder_excluded_file');

        FS::mkdir('vendor-bin');
        FS::touch('vendor-bin/file1');

        chdir($this->tmp);

        $this->setConfig([
            'base-path' => 'sub-dir',
            'files' => [
                'file0',
                'file1',
            ],
            'directories' => [
                'B',
                'C',
            ],
            'finder' => [
                [
                    'in' => [
                        'D',
                    ],
                    'name' => 'fileD*',
                ],
                [
                    'in' => [
                        'E',
                    ],
                    'name' => 'fileE*',
                ],
            ],
            'blacklist' => [
                'file1',
                'B/fileB1',
                'C/fileC1',
                'D/fileD1',
                'E/fileE1',
                'glob_finder_excluded_file',
                'glob-finder_excluded_file',
                'vendor-bin',
            ],
        ]);

        // Relative to the current working directory for readability
        $expected = [
            'sub-dir/B/fileB0',
            'sub-dir/C/fileC0',
            'sub-dir/D/fileD0',
            'sub-dir/E/fileE0',
            'sub-dir/file0',
            'sub-dir/file1',
        ];

        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertSame($expected, $actual);
        self::assertCount(0, $this->config->getBinaryFiles());
    }

    public function test_configured_files_are_relative_to_base_path_unless_they_are_absolute_paths(): void
    {
        FS::mkdir('sub-dir');
        chdir('sub-dir');

        FS::touch('file0');
        FS::touch('file1');

        FS::mkdir('B');
        FS::touch('B/fileB0');
        FS::touch('B/fileB1');

        FS::mkdir('C');
        FS::touch('C/fileC0');
        FS::touch('C/fileC1');

        FS::mkdir('D');
        FS::touch('D/fileD0');
        FS::touch('D/fileD1');
        FS::touch('D/finder_excluded_file');

        FS::mkdir('E');
        FS::touch('E/fileE0');
        FS::touch('E/fileE1');
        FS::touch('E/finder_excluded_file');

        chdir($this->tmp);

        $basePath = $this->tmp.DIRECTORY_SEPARATOR.'sub-dir'.DIRECTORY_SEPARATOR;

        $this->setConfig([
            'files' => [
                $basePath.'file0',
                $basePath.'file1',
            ],
            'directories' => [
                $basePath.'B',
                $basePath.'../sub-dir/C/',
            ],
            'finder' => [
                [
                    'in' => [
                        $basePath.'D',
                    ],
                    'name' => 'fileD*',
                ],
                [
                    'in' => [
                        $basePath.'E',
                    ],
                    'name' => 'fileE*',
                ],
            ],
            'blacklist' => [
                $basePath.'file1',
                $basePath.'B/fileB1',
                $basePath.'C/fileC1',
                $basePath.'D/fileD1',
                $basePath.'E/fileE1',
            ],
        ]);

        // Relative to the current working directory for readability
        $expected = [
            'sub-dir/B/fileB0',
            'sub-dir/C/fileC0',
            'sub-dir/D/fileD0',
            'sub-dir/E/fileE0',
            'sub-dir/file0',
            'sub-dir/file1',
        ];

        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertSame($expected, $actual);
        self::assertCount(0, $this->config->getBinaryFiles());
    }

    public function test_the_files_belonging_to_dev_packages_are_ignored_only_in_the_finder_config(): void
    {
        FS::dumpFile('composer.json', '{}');
        FS::dumpFile(
            'composer.lock',
            <<<'JSON'
                {
                    "packages-dev": [
                        {"name": "acme/foo"},
                        {"name": "acme/bar"},
                        {"name": "acme/oof"}
                    ]
                }
                JSON,
        );
        FS::dumpFile('vendor/composer/installed.json', '{}');

        FS::touch('file0');
        FS::touch('file1');

        FS::dumpFile('vendor/acme/foo/af0');
        FS::dumpFile('vendor/acme/foo/af1');

        FS::dumpFile('vendor/acme/bar/ab0');
        FS::dumpFile('vendor/acme/bar/ab1');

        FS::dumpFile('vendor/acme/oof/ao0');
        FS::dumpFile('vendor/acme/oof/ao1');

        FS::mkdir('C');
        FS::touch('C/fileC0');
        FS::touch('C/fileC1');

        FS::mkdir('D');
        FS::touch('D/fileD0');
        FS::touch('D/fileD1');
        FS::touch('D/finder_excluded_file');

        FS::mkdir('E');
        FS::touch('E/fileE0');
        FS::touch('E/fileE1');
        FS::touch('E/finder_excluded_file');

        $this->setConfig([
            'files' => [
                'file0',
                'file1',
                'vendor/acme/foo/af0',
                'vendor/acme/foo/af1',
            ],
            'directories' => [
                'vendor/acme/bar',
                'C',
            ],
            'finder' => [
                [
                    'in' => [
                        'D',
                    ],
                    'name' => 'fileD*',
                ],
                [
                    'in' => [
                        'E',
                    ],
                    'name' => 'fileE*',
                ],
                [
                    'in' => [
                        'vendor/acme/oof',
                    ],
                ],
            ],
            'blacklist' => [
                'file1',
                'B/fileB1',
                'C/fileC1',
                'D/fileD1',
                'E/fileE1',
            ],
        ]);

        // Relative to the current working directory for readability
        $expected = [
            'C/fileC0',
            'composer.json',
            'composer.lock',
            'D/fileD0',
            'E/fileE0',
            'file0',
            'file1',    // 'files' & 'files-bin' are not affected by the blacklist filter
            'vendor/acme/bar/ab0',
            'vendor/acme/bar/ab1',
            'vendor/acme/foo/af0',
            'vendor/acme/foo/af1',
            'vendor/composer/installed.json',
        ];

        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertSame($expected, $actual);
        self::assertCount(0, $this->config->getBinaryFiles());
    }

    public function test_a_non_existent_file_cannot_be_added_to_the_list_of_files(): void
    {
        try {
            $this->setConfig([
                'files' => [
                    'non-existent',
                ],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $filePath = Path::makeAbsolute('non-existent', $this->tmp);

            self::assertSame(
                sprintf(
                    '"files" must contain a list of existing files. Could not find "%s".',
                    $filePath,
                ),
                $exception->getMessage(),
            );
        }
    }

    public function test_symlinks_are_not_supported_in_finder_in_setting(): void
    {
        FS::mkdir('sub-dir');

        FS::rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        FS::mkdir('F');
        FS::touch('F/fileF0');
        FS::touch('F/fileF1');
        FS::touch('F/finder_excluded_file');

        FS::symlink('F', 'sub-dir/F');

        try {
            $this->setConfig([
                'base-path' => 'sub-dir',
                'finder' => [
                    [
                        'in' => [
                            'F',
                        ],
                        'name' => 'fileF*',
                    ],
                ],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $link = $this->tmp.'/sub-dir/F';

            self::assertSame(
                "Cannot append the link \"{$link}\" to the Finder: links are not supported.",
                $exception->getMessage(),
            );
        }
    }

    public function test_appending_a_file_from_a_symlinked_directory_is_not_supported(): void
    {
        FS::mkdir('sub-dir');

        FS::rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        FS::mkdir('F');
        FS::touch('F/fileF0');
        FS::touch('F/fileF1');
        FS::touch('F/finder_excluded_file');

        FS::symlink('F', 'sub-dir/F');

        try {
            $this->setConfig([
                'base-path' => 'sub-dir',
                'finder' => [
                    [
                        'append' => [
                            'F/fileF0',
                        ],
                        'name' => 'fileF*',
                    ],
                ],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $link = $this->tmp.'/sub-dir/F/fileF0';

            self::assertSame(
                "Path \"{$link}\" was expected to be a file or directory. It may be a symlink (which are unsupported).",
                $exception->getMessage(),
            );
        }
    }

    public function test_appending_a_symlinked_file_is_not_supported(): void
    {
        FS::mkdir('sub-dir');

        FS::rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        FS::mkdir('F');
        FS::touch('F/fileF0');
        FS::touch('F/fileF1');
        FS::touch('F/finder_excluded_file');

        FS::symlink('F/fileF0', 'sub-dir/F/fileF0');

        try {
            $this->setConfig([
                'base-path' => 'sub-dir',
                'finder' => [
                    [
                        'append' => [
                            'F/fileF0',
                        ],
                        'name' => 'fileF*',
                    ],
                ],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $link = $this->tmp.'/sub-dir/F/fileF0';

            self::assertSame(
                "Cannot append the link \"{$link}\" to the Finder: links are not supported.",
                $exception->getMessage(),
            );
        }
    }

    public function test_configuring_a_symlink_file_is_not_supported(): void
    {
        FS::mkdir('sub-dir');

        FS::rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        FS::mkdir('F');
        FS::touch('F/fileF0');
        FS::touch('F/fileF1');
        FS::touch('F/finder_excluded_file');

        FS::symlink('F/fileF0', 'sub-dir/F/fileF0');

        try {
            $this->setConfig([
                'base-path' => 'sub-dir',
                'files' => [
                    'F/fileF0',
                ],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $link = $this->tmp.'/sub-dir/F/fileF0';

            self::assertSame(
                "Cannot add the link \"{$link}\": links are not supported.",
                $exception->getMessage(),
            );
        }
    }

    public function test_configuring_a_symlink_directory_is_not_supported(): void
    {
        FS::mkdir('sub-dir');

        FS::rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        FS::mkdir('F');
        FS::touch('F/fileF0');
        FS::touch('F/fileF1');
        FS::touch('F/finder_excluded_file');

        FS::symlink('F', 'sub-dir/F');

        try {
            $this->setConfig([
                'base-path' => 'sub-dir',
                'directories' => [
                    'F',
                ],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $link = $this->tmp.'/sub-dir/F';

            self::assertSame(
                "Cannot add the link \"{$link}\": links are not supported.",
                $exception->getMessage(),
            );
        }
    }

    public function test_cannot_add_a_directory_to_the_list_of_files(): void
    {
        FS::mkdir('dirA');

        try {
            $this->setConfig([
                'files' => [
                    'dirA',
                ],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $filePath = $this->tmp.DIRECTORY_SEPARATOR.'dirA';

            self::assertSame(
                sprintf(
                    '"files" must contain a list of existing files. Could not find "%s".',
                    $filePath,
                ),
                $exception->getMessage(),
            );
        }
    }

    public function test_cannot_add_a_non_existent_directory_to_the_list_of_directories(): void
    {
        try {
            $this->setConfig([
                'directories' => [
                    'non-existent',
                ],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $dirPath = $this->tmp.DIRECTORY_SEPARATOR.'non-existent';

            self::assertSame(
                sprintf(
                    '"directories" must contain a list of existing directories. Could not find "%s".',
                    $dirPath,
                ),
                $exception->getMessage(),
            );
        }
    }

    public function test_cannot_add_a_file_to_the_list_of_directories(): void
    {
        FS::touch('foo');

        try {
            $this->setConfig([
                'directories' => [
                    'foo',
                ],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $dirPath = $this->tmp.DIRECTORY_SEPARATOR.'foo';

            self::assertSame(
                sprintf(
                    '"directories" must contain a list of existing directories. Could not find "%s".',
                    $dirPath,
                ),
                $exception->getMessage(),
            );
        }
    }

    public function test_the_bin_files_iterator_can_be_configured(): void
    {
        FS::touch('file0');
        FS::touch('file1');

        FS::mkdir('B');
        FS::touch('B/fileB0');
        FS::touch('B/fileB1');

        FS::mkdir('C');
        FS::touch('C/fileC0');
        FS::touch('C/fileC1');

        FS::mkdir('D');
        FS::touch('D/fileD0');
        FS::touch('D/fileD1');
        FS::touch('D/finder_excluded_file');

        FS::mkdir('E');
        FS::touch('E/fileE0');
        FS::touch('E/fileE1');
        FS::touch('E/finder_excluded_file');

        FS::mkdir('F');
        FS::touch('F/fileF0');
        FS::touch('F/fileF1');

        $this->setConfig([
            'files-bin' => [
                'file0',
                'file1',
            ],
            'directories-bin' => [
                'B',
                'C',
            ],
            'finder-bin' => [
                [
                    'in' => [
                        'D',
                    ],
                    'name' => 'fileD*',
                    'append' => ['F/fileF0'],
                ],
                [
                    'in' => [
                        'E',
                    ],
                    'name' => 'fileE*',
                    'append' => ['F/fileF1'],
                ],
            ],
            'blacklist' => [
                'file1',
                'B/fileB1',
                'C/fileC1',
                'D/fileD1',
                'E/fileE1',
            ],
        ]);

        // Relative to the current working directory for readability
        $expected = [
            'B/fileB0',
            'C/fileC0',
            'D/fileD0',
            'E/fileE0',
            'F/fileF0',
            'F/fileF1',
            'file0',
            'file1',    // 'files' & 'files-bin' are not affected by the blacklist filter
        ];

        $actual = $this->normalizePaths($this->config->getBinaryFiles());

        self::assertSame($expected, $actual);
        self::assertCount(0, $this->config->getFiles());
    }

    public function test_configured_bin_files_are_relative_to_base_path(): void
    {
        FS::mkdir('sub-dir');

        FS::rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        chdir('sub-dir');

        FS::touch('file0');
        FS::touch('file1');

        FS::mkdir('B');
        FS::touch('B/fileB0');
        FS::touch('B/fileB1');

        FS::mkdir('C');
        FS::touch('C/fileC0');
        FS::touch('C/fileC1');

        FS::mkdir('D');
        FS::touch('D/fileD0');
        FS::touch('D/fileD1');
        FS::touch('D/finder_excluded_file');

        FS::mkdir('E');
        FS::touch('E/fileE0');
        FS::touch('E/fileE1');
        FS::touch('E/finder_excluded_file');

        chdir($this->tmp);

        $this->setConfig([
            'base-path' => 'sub-dir',
            'files-bin' => [
                'file0',
                'file1',
            ],
            'directories-bin' => [
                'B',
                'C',
            ],
            'finder-bin' => [
                [
                    'in' => [
                        'D',
                    ],
                    'name' => 'fileD*',
                ],
                [
                    'in' => [
                        'E',
                    ],
                    'name' => 'fileE*',
                ],
            ],
            'blacklist' => [
                'file1',
                'B/fileB1',
                'C/fileC1',
                'D/fileD1',
                'E/fileE1',
            ],
        ]);

        // Relative to the current working directory for readability
        $expected = [
            'sub-dir/B/fileB0',
            'sub-dir/C/fileC0',
            'sub-dir/D/fileD0',
            'sub-dir/E/fileE0',
            'sub-dir/file0',
            'sub-dir/file1',
        ];

        $actual = $this->normalizePaths($this->config->getBinaryFiles());

        self::assertSame($expected, $actual);
        self::assertCount(0, $this->config->getFiles());
    }

    public function test_configured_bin_files_are_relative_to_base_path_unless_they_are_absolute_paths(): void
    {
        FS::mkdir('sub-dir');
        chdir('sub-dir');

        FS::touch('file0');
        FS::touch('file1');

        FS::mkdir('B');
        FS::touch('B/fileB0');
        FS::touch('B/fileB1');

        FS::mkdir('C');
        FS::touch('C/fileC0');
        FS::touch('C/fileC1');

        FS::mkdir('D');
        FS::touch('D/fileD0');
        FS::touch('D/fileD1');
        FS::touch('D/finder_excluded_file');

        FS::mkdir('E');
        FS::touch('E/fileE0');
        FS::touch('E/fileE1');
        FS::touch('E/finder_excluded_file');

        chdir($this->tmp);

        $basePath = $this->tmp.DIRECTORY_SEPARATOR.'sub-dir'.DIRECTORY_SEPARATOR;

        $this->setConfig([
            'files-bin' => [
                $basePath.'file0',
                $basePath.'file1',
            ],
            'directories-bin' => [
                $basePath.'B',
                $basePath.'C',
            ],
            'finder-bin' => [
                [
                    'in' => [
                        $basePath.'D',
                    ],
                    'name' => 'fileD*',
                ],
                [
                    'in' => [
                        $basePath.'E',
                    ],
                    'name' => 'fileE*',
                ],
            ],
            'blacklist' => [
                $basePath.'file1',
                $basePath.'B/fileB1',
                $basePath.'C/fileC1',
                $basePath.'D/fileD1',
                $basePath.'E/fileE1',
            ],
        ]);

        // Relative to the current working directory for readability
        $expected = [
            'sub-dir/B/fileB0',
            'sub-dir/C/fileC0',
            'sub-dir/D/fileD0',
            'sub-dir/E/fileE0',
            'sub-dir/file0',
            'sub-dir/file1',
        ];

        $actual = $this->normalizePaths($this->config->getBinaryFiles());

        self::assertSame($expected, $actual);
        self::assertCount(0, $this->config->getFiles());
    }

    public function test_cannot_add_a_non_existent_bin_file_to_the_list_of_files(): void
    {
        try {
            $this->setConfig([
                'files-bin' => [
                    'non-existent',
                ],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $filePath = $this->tmp.DIRECTORY_SEPARATOR.'non-existent';

            self::assertSame(
                sprintf(
                    '"files-bin" must contain a list of existing files. Could not find "%s".',
                    $filePath,
                ),
                $exception->getMessage(),
            );
        }
    }

    public function test_cannot_add_a_directory_to_the_list_of_bin_files(): void
    {
        FS::mkdir('dirA');

        try {
            $this->setConfig([
                'files-bin' => [
                    'dirA',
                ],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $filePath = $this->tmp.DIRECTORY_SEPARATOR.'dirA';

            self::assertSame(
                sprintf(
                    '"files-bin" must contain a list of existing files. Could not find "%s".',
                    $filePath,
                ),
                $exception->getMessage(),
            );
        }
    }

    public function test_cannot_add_a_non_existent_directory_to_the_list_of_bin_directories(): void
    {
        try {
            $this->setConfig([
                'directories-bin' => [
                    'non-existent',
                ],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $dirPath = $this->tmp.DIRECTORY_SEPARATOR.'non-existent';

            self::assertSame(
                sprintf(
                    '"directories-bin" must contain a list of existing directories. Could not find "%s".',
                    $dirPath,
                ),
                $exception->getMessage(),
            );
        }
    }

    public function test_cannot_add_a_file_to_the_list_of_bin_directories(): void
    {
        FS::touch('foo');

        try {
            $this->setConfig([
                'directories-bin' => [
                    'foo',
                ],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $dirPath = $this->tmp.DIRECTORY_SEPARATOR.'foo';

            self::assertSame(
                sprintf(
                    '"directories-bin" must contain a list of existing directories. Could not find "%s".',
                    $dirPath,
                ),
                $exception->getMessage(),
            );
        }
    }

    public function test_the_cannot_be_included_twice(): void
    {
        FS::mkdir('A');
        FS::touch('A/foo');

        FS::mkdir('B');
        FS::touch('B/bar');

        $this->setConfig([
            'files' => [
                'A/foo',
                'B/bar',
            ],
            'directories' => ['A', 'B'],
            'finder' => [
                [
                    'in' => ['A', 'B'],
                ],
                [
                    'in' => ['A', 'B'],
                ],
            ],

            'files-bin' => [
                'A/foo',
                'B/bar',
            ],
            'directories-bin' => ['A', 'B'],
            'finder-bin' => [
                [
                    'in' => ['A', 'B'],
                ],
                [
                    'in' => ['A', 'B'],
                ],
            ],
        ]);

        // Relative to the current working directory for readability
        $expected = [
            'A/foo',
            'B/bar',
        ];

        self::assertSame(
            $expected,
            $this->normalizePaths($this->config->getFiles()),
        );
        self::assertSame(
            $expected,
            $this->normalizePaths($this->config->getBinaryFiles()),
        );
    }

    public function test_a_recommendation_is_given_if_the_blacklist_is_set_with_its_default_value(): void
    {
        $this->setConfig([
            'blacklist' => [],
        ]);

        self::assertSame(
            ['The "blacklist" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    #[DataProvider('jsonValidNonStringArrayProvider')]
    public function test_blacklist_value_must_be_an_array_of_strings(mixed $value): void
    {
        try {
            $this->setConfig([
                'blacklist' => $value,
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (JsonValidationException $exception) {
            self::assertMatchesRegularExpression(
                '/does not match the expected JSON schema:/',
                $exception->getMessage(),
            );
        }
    }

    public function test_the_blacklist_input_is_normalized(): void
    {
        FS::mkdir('B');
        FS::touch('B/fileB0');
        FS::touch('B/fileB1');

        $this->setConfig([
            'directories' => [
                'B',
            ],
            'blacklist' => [
                ' B/fileB1 ',
            ],
        ]);

        // Relative to the current working directory for readability
        $expected = [
            'B/fileB0',
        ];
        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertSame($expected, $actual);
    }

    public function test_the_blacklist_input_may_refer_to_non_existent_paths(): void
    {
        $this->setConfig([
            'blacklist' => [
                '/nowhere',
            ],
        ]);

        // Relative to the current working directory for readability
        $expected = [];
        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertSame($expected, $actual);
    }

    #[DataProvider('jsonValidNonStringArrayProvider')]
    public function test_files_value_must_be_an_array_of_strings(mixed $value): void
    {
        try {
            $this->setConfig([
                'files' => $value,
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (JsonValidationException $exception) {
            self::assertMatchesRegularExpression(
                '/does not match the expected JSON schema:/',
                $exception->getMessage(),
            );
        }
    }

    public function test_a_recommendation_is_given_when_the_files_are_set_to_an_empty_array(): void
    {
        $this->setConfig([
            'files' => [],
        ]);

        self::assertSame(
            ['The "files" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    #[DataProvider('jsonValidNonStringArrayProvider')]
    public function test_bin_files_value_must_be_an_array_of_strings(mixed $value): void
    {
        try {
            $this->setConfig([
                'files-bin' => $value,
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (JsonValidationException $exception) {
            self::assertMatchesRegularExpression(
                '/does not match the expected JSON schema:/',
                $exception->getMessage(),
            );
        }
    }

    public function test_the_files_and_bin_files_input_is_normalized(): void
    {
        FS::touch('foo');

        $this->setConfig([
            'files' => [
                ' foo ',
            ],
            'files-bin' => [
                ' foo ',
            ],
        ]);

        // Relative to the current working directory for readability
        $expected = ['foo'];

        self::assertSame(
            $expected,
            $this->normalizePaths($this->config->getFiles()),
        );
        self::assertSame(
            $expected,
            $this->normalizePaths($this->config->getBinaryFiles()),
        );
    }

    #[DataProvider('jsonValidNonStringArrayProvider')]
    public function test_directories_value_must_be_an_array_of_strings(mixed $value): void
    {
        try {
            $this->setConfig([
                'directories' => $value,
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (JsonValidationException $exception) {
            self::assertMatchesRegularExpression(
                '/does not match the expected JSON schema:/',
                $exception->getMessage(),
            );
        }
    }

    public function test_a_recommendation_is_given_when_an_emtpy_array_is_given_for_directories(): void
    {
        $this->setConfig([
            'directories' => [],
        ]);

        self::assertSame(
            ['The "directories" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    #[DataProvider('jsonValidNonStringArrayProvider')]
    public function test_bin_directories_value_must_be_an_array_of_strings(mixed $value): void
    {
        try {
            $this->setConfig([
                'directories-bin' => $value,
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (JsonValidationException $exception) {
            self::assertMatchesRegularExpression(
                '/does not match the expected JSON schema:/',
                $exception->getMessage(),
            );
        }
    }

    public function test_the_directories_and_bin_directories_input_is_normalized(): void
    {
        FS::mkdir('A');
        FS::touch('A/foo');

        $this->setConfig([
            'directories' => [
                ' A ',
            ],
            'directories-bin' => [
                ' A ',
            ],
        ]);

        // Relative to the current working directory for readability
        $expected = ['A/foo'];

        self::assertSame(
            $expected,
            $this->normalizePaths($this->config->getFiles()),
        );
        self::assertSame(
            $expected,
            $this->normalizePaths($this->config->getBinaryFiles()),
        );
    }

    #[DataProvider('jsonValidNonObjectArrayProvider')]
    public function test_finder_value_must_be_an_array_of_objects(mixed $value): void
    {
        try {
            $this->setConfig([
                'finder' => $value,
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (JsonValidationException $exception) {
            self::assertMatchesRegularExpression(
                '/does not match the expected JSON schema:/',
                $exception->getMessage(),
            );
        }
    }

    public function test_a_recommendation_is_given_when_an_emtpy_array_is_given_for_finders(): void
    {
        $this->setConfig([
            'finder' => [],
        ]);

        self::assertSame(
            ['The "finder" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_finder_and_bin_finder_input_is_normalized(): void
    {
        FS::mkdir('sub-dir');

        FS::rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        chdir('sub-dir');

        FS::mkdir('A');
        FS::touch('A/foo');

        FS::mkdir('A/D0');
        FS::touch('A/D0/da0');

        FS::mkdir('A/D1');
        FS::touch('A/D1/da1');

        FS::mkdir('B');
        FS::touch('B/bar');

        FS::mkdir('D');
        FS::touch('D/doo');

        FS::mkdir('D/D0');
        FS::touch('D/D0/d0o');

        FS::mkdir('D/D1');
        FS::touch('D/D1/d1o');

        FS::touch('oof');
        FS::touch('rab');

        chdir($this->tmp);

        $finderConfig = [
            [
                ' in ' => [' A ', ' B ', ' D '],
                ' exclude ' => [' D0 ', ' D1 '],
                ' append ' => [' oof ', ' rab '],
            ],
        ];

        $this->setConfig([
            'base-path' => 'sub-dir',
            'finder' => $finderConfig,
            'finder-bin' => $finderConfig,
        ]);

        // Relative to the current working directory for readability
        $expected = [
            'sub-dir/A/foo',
            'sub-dir/B/bar',
            'sub-dir/D/doo',
            'sub-dir/oof',
            'sub-dir/rab',
        ];

        self::assertEqualsCanonicalizing(
            $expected,
            $this->normalizePaths($this->config->getFiles()),
        );
        self::assertEqualsCanonicalizing(
            $expected,
            $this->normalizePaths($this->config->getBinaryFiles()),
        );
    }

    public function test_finder_and_bin_finder_exclude_files_or_directories_may_not_exists(): void
    {
        FS::mkdir('A');
        FS::touch('A/foo');

        $finderConfig = [
            [
                'in' => ['A'],
                'exclude' => ['unknown'],
            ],
        ];

        $this->setConfig([
            'finder' => $finderConfig,
            'finder-bin' => $finderConfig,
        ]);

        // Relative to the current working directory for readability
        $expected = ['A/foo'];

        self::assertEqualsCanonicalizing(
            $expected,
            $this->normalizePaths($this->config->getFiles()),
        );
        self::assertEqualsCanonicalizing(
            $expected,
            $this->normalizePaths($this->config->getBinaryFiles()),
        );
    }

    public function test_finder_array_arguments_are_called_as_single_arguments(): void
    {
        FS::mkdir('A');
        FS::touch('A/foo');

        FS::mkdir('B');
        FS::touch('B/bar');

        $this->setConfig([
            'files' => [],
            'finder' => [
                [
                    // This would cause a failure on the Finder as `Finder::name()` accepts only a string value. But
                    // instead here we will do multiple call of `Finder::name()` with each value
                    'name' => [
                        'fo*',
                        'bar*',
                    ],
                    'in' => $this->tmp,
                ],
            ],
        ]);

        // Relative to the current working directory for readability
        $expected = [
            'A/foo',
            'B/bar',
        ];
        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertSame($expected, $actual);
    }

    public function test_the_finder_config_cannot_include_invalid_methods(): void
    {
        try {
            $this->setConfig([
                'finder' => [
                    ['invalidMethod' => 'whargarbl'],
                ],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Expected the method "invalidMethod" to exist.',
                $exception->getMessage(),
            );
        }
    }

    public function test_the_composer_json_and_lock_files_are_always_included_even_when_the_user_configure_which_files_to_pick(): void
    {
        FS::touch('file0');
        FS::touch('file1');

        FS::mkdir('B');
        FS::touch('B/fileB0');
        FS::touch('B/fileB1');

        FS::dumpFile('composer.json', '{}');
        FS::dumpFile('composer.lock', '{}');

        $this->setConfig([
            'files' => [
                'file0',
                'file1',
            ],
            'directories' => ['B'],
        ]);

        // Relative to the current working directory for readability
        $expected = [
            'B/fileB0',
            'B/fileB1',
            'composer.json',
            'composer.lock',
            'file0',
            'file1',
        ];

        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertSame($expected, $actual);
        self::assertCount(0, $this->config->getBinaryFiles());

        $this->setConfig([
            'directories' => ['B'],
        ]);

        // Relative to the current working directory for readability
        $expected = [
            'B/fileB0',
            'B/fileB1',
            'composer.json',
            'composer.lock',
        ];

        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertSame($expected, $actual);
        self::assertCount(0, $this->config->getBinaryFiles());
    }

    public function test_files_are_autodiscovered_by_default(): void
    {
        self::assertTrue($this->config->hasAutodiscoveredFiles());
    }

    #[DataProvider('filesAutodiscoveryConfigProvider')]
    public function test_files_are_autodiscovered_unless_directory_or_finder_config_is_provided(
        callable $setUp,
        array $config,
        bool $expectedFilesAutodiscovery,
    ): void {
        $setUp();

        $this->setConfig($config);

        self::assertSame($expectedFilesAutodiscovery, $this->config->hasAutodiscoveredFiles());
    }

    public function test_append_autodiscovered_files_to_configured_files_if_the_autodiscovery_is_forced(): void
    {
        FS::touch('file0');
        FS::touch('file1');
        FS::touch('file2');

        FS::mkdir('B');
        FS::touch('B/fileB0');
        FS::touch('B/fileB1');

        FS::mkdir('PSR4_0');
        FS::touch('PSR4_0/file0');
        FS::touch('PSR4_0/file1');

        FS::mkdir('PSR4_1');
        FS::touch('PSR4_1/file0');
        FS::touch('PSR4_1/file1');

        FS::mkdir('PSR4_2');
        FS::touch('PSR4_2/file0');
        FS::touch('PSR4_2/file1');

        FS::mkdir('DEV_PSR4_0');
        FS::touch('DEV_PSR4_0/file0');
        FS::touch('DEV_PSR4_0/file1');

        FS::mkdir('PSR0_0');
        FS::touch('PSR0_0/file0');
        FS::touch('PSR0_0/file1');

        FS::mkdir('PSR0_1');
        FS::touch('PSR0_1/file0');
        FS::touch('PSR0_1/file1');

        FS::mkdir('PSR0_2');
        FS::touch('PSR0_2/file0');
        FS::touch('PSR0_2/file1');

        FS::mkdir('DEV_PSR0_0');
        FS::touch('DEV_PSR0_0/file0');
        FS::touch('DEV_PSR0_0/file1');

        FS::mkdir('CLASSMAP_DIR');
        FS::touch('CLASSMAP_DIR/file0');
        FS::touch('CLASSMAP_DIR/file1');

        FS::mkdir('CLASSMAP_DEV_DIR');
        FS::touch('CLASSMAP_DEV_DIR/file0');
        FS::touch('CLASSMAP_DEV_DIR/file1');

        FS::mkdir('dir0');
        FS::touch('dir0/file0');
        FS::touch('dir0/file1');
        FS::touch('dir0/blacklisted_file');

        FS::mkdir('dir1');
        FS::touch('dir1/file0');
        FS::touch('dir1/file1');
        FS::touch('dir1/blacklisted_file');

        FS::dumpFile(
            'composer.json',
            <<<'JSON'
                {
                    "autoload": {
                        "files": ["file0", "file1"],
                        "psr-4": {
                            "Acme\\": "PSR4_0",
                            "Bar\\": ["PSR4_1", "PSR4_2"]
                        },
                        "psr-0": {
                            "Acme\\": "PSR0_0",
                            "Bar\\": ["PSR0_1", "PSR0_2"]
                        },
                        "classmap": ["CLASSMAP_DIR"]
                    },
                    "autoload-dev": {
                        "files": ["file2"],
                        "psr-4": {
                            "Acme\\": "DEV_PSR4_0"
                        },
                        "psr-0": {
                            "Acme\\": "DEV_PSR0_0"
                        },
                        "classmap": ["CLASSMAP_DEV_DIR"]
                    }
                }
                JSON,
        );

        // Relative to the current working directory for readability
        $expected = [
            'CLASSMAP_DIR/file0',
            'CLASSMAP_DIR/file1',
            'composer.json',
            'dir0/file0',
            'dir0/file1',
            'dir1/file0',
            'dir1/file1',
            'file0',
            'file1',
            'PSR0_0/file0',
            'PSR0_0/file1',
            'PSR0_1/file0',
            'PSR0_1/file1',
            'PSR0_2/file0',
            'PSR0_2/file1',
            'PSR4_0/file0',
            'PSR4_0/file1',
            'PSR4_1/file0',
            'PSR4_1/file1',
            'PSR4_2/file0',
            'PSR4_2/file1',
        ];

        $this->setConfig([
            'directories' => ['dir0'],
            'finder' => [
                ['in' => ['dir1']],
            ],
            'force-autodiscovery' => true,
            'blacklist' => [
                'dir0/blacklisted_file',
                'dir1/blacklisted_file',
            ],
        ]);

        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertEquals($expected, $actual);

        self::assertCount(0, $this->config->getBinaryFiles());
    }

    public function test_a_recommendation_is_given_when_the_force_autodiscovery_is_set_to_false(): void
    {
        $this->setConfig([
            'force-autodiscovery' => false,
        ]);

        self::assertSame(
            ['The "force-autodiscovery" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_no_warning_is_given_when_no_installed_json_no_composer_lock_are_found(): void
    {
        $config = Configuration::create(
            $configPath = self::FIXTURES_DIR.'/dir000/box.json',
            json_decode(file_get_contents($configPath)),
        );

        self::assertSame([], $config->getRecommendations());
        self::assertSame([], $config->getWarnings());
    }

    public function test_no_warning_is_given_when_the_installed_json_and_composer_lock_are_found(): void
    {
        $config = Configuration::create(
            $configPath = self::FIXTURES_DIR.'/dir001/box.json',
            json_decode(file_get_contents($configPath)),
        );

        self::assertTrue($config->dumpAutoload());

        self::assertSame([], $config->getRecommendations());
        self::assertSame([], $config->getWarnings());
    }

    public function test_no_warning_is_given_when_the_installed_json_is_found_and_the_composer_lock_is_not_when_the_composer_autoloader_is_not_dumped(): void
    {
        $configPath = self::FIXTURES_DIR.'/dir002/box.json';

        $config = Configuration::create(
            $configPath,
            json_decode(file_get_contents($configPath)),
        );

        self::assertFalse($config->dumpAutoload());

        self::assertSame([], $config->getRecommendations());
        self::assertSame([], $config->getWarnings());
    }

    public function test_a_warning_is_given_when_no_installed_json_is_found_and_the_composer_lock_is_when_the_composer_autoloader_is_dumped(): void
    {
        $configPath = self::FIXTURES_DIR.'/dir002/box.json';

        $decodedConfig = json_decode(file_get_contents($configPath));
        $decodedConfig->{'dump-autoload'} = true;

        $config = Configuration::create($configPath, $decodedConfig);

        self::assertFalse($config->dumpAutoload());

        self::assertSame([], $config->getRecommendations());
        self::assertSame(
            [
                'The "dump-autoload" setting has been set but has been ignored because the composer.json, composer.lock '
                .'and vendor/composer/installed.json files are necessary but could not be found.',
            ],
            $config->getWarnings(),
        );
    }

    public function test_no_warning_is_given_when_the_installed_json_is_found_and_the_composer_lock_is_not_when_the_autoloader_is_not_dumped(): void
    {
        $configPath = self::FIXTURES_DIR.'/dir003/box.json';

        $decodedConfig = json_decode(file_get_contents($configPath));
        unset($decodedConfig->{'dump-autoload'});

        $config = Configuration::create($configPath, $decodedConfig);

        self::assertFalse($config->dumpAutoload());

        self::assertSame([], $config->getRecommendations());
        self::assertSame([], $config->getWarnings());
    }

    public function test_a_warning_is_given_when_the_installed_json_is_found_and_the_composer_lock_is_not(): void
    {
        $configPath = self::FIXTURES_DIR.'/dir003/box.json';

        $config = Configuration::create(
            $configPath,
            json_decode(file_get_contents($configPath)),
        );

        self::assertFalse($config->dumpAutoload());

        self::assertSame([], $config->getRecommendations());
        self::assertSame(
            [
                'The "dump-autoload" setting has been set but has been ignored because the composer.json, composer.lock '
                .'and vendor/composer/installed.json files are necessary but could not be found.',
            ],
            $config->getWarnings(),
        );
    }

    public function test_no_warning_is_given_when_the_installed_json_is_found_and_the_composer_lock_is_not_and_the_dump_autoload_disabled(): void
    {
        $configPath = self::FIXTURES_DIR.'/dir004/box.json';

        $config = Configuration::create(
            $configPath,
            json_decode(file_get_contents($configPath)),
        );

        self::assertFalse($config->dumpAutoload());

        self::assertSame([], $config->getRecommendations());
        self::assertSame([], $config->getWarnings());
    }

    public function test_dev_files_are_excluded_or_included_depending_of_the_exclude_dev_files_setting(): void
    {
        FS::dumpFile('composer.json', '{}');
        FS::dumpFile(
            'composer.lock',
            <<<'JSON'
                {
                    "packages": [
                        {"name": "acme/foo"}
                    ],
                    "packages-dev": [
                        {"name": "acme/bar"},
                        {"name": "acme/oof"}
                    ]
                }
                JSON,
        );
        FS::dumpFile('vendor/composer/installed.json', '{}');

        FS::dumpFile('vendor/acme/foo/af0');
        FS::dumpFile('vendor/acme/foo/af1');

        FS::dumpFile('vendor/acme/bar/ab0');
        FS::dumpFile('vendor/acme/bar/ab1');

        FS::dumpFile('vendor/acme/oof/ao0');
        FS::dumpFile('vendor/acme/oof/ao1');

        $this->reloadConfig();

        self::assertTrue($this->config->excludeDevFiles());

        // Relative to the current working directory for readability
        $expected = [
            'box.json',
            'composer.json',
            'composer.lock',
            'vendor/acme/foo/af0',
            'vendor/acme/foo/af1',
            'vendor/composer/installed.json',
        ];

        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertSame($expected, $actual);
        self::assertCount(0, $this->config->getBinaryFiles());

        $this->setConfig(['exclude-dev-files' => false]);

        self::assertFalse($this->config->excludeDevFiles());

        // Relative to the current working directory for readability
        $expected = [
            'box.json',
            'composer.json',
            'composer.lock',
            'vendor/acme/bar/ab0',
            'vendor/acme/bar/ab1',
            'vendor/acme/foo/af0',
            'vendor/acme/foo/af1',
            'vendor/acme/oof/ao0',
            'vendor/acme/oof/ao1',
            'vendor/composer/installed.json',
        ];

        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertSame($expected, $actual);
        self::assertCount(0, $this->config->getBinaryFiles());
    }

    public static function configWithMainScriptProvider(): iterable
    {
        yield [
            static function (): void {
                FS::touch('main-script');
                FS::touch('file0');
                FS::touch('file1');
            },
            [
                'main' => 'main-script',
                'files' => [
                    'main-script',
                    'file0',
                ],
                'files-bin' => [
                    'main-script',
                    'file1',
                ],
            ],
            ['file0'],
            ['file1'],
        ];

        yield [
            static function (): void {
                FS::mkdir('sub-dir');

                FS::touch('sub-dir/main-script');
                FS::touch('sub-dir/file0');
                FS::touch('sub-dir/file1');
            },
            [
                'base-path' => 'sub-dir',
                'main' => 'main-script',
                'files' => [
                    'main-script',
                    'file0',
                ],
                'files-bin' => [
                    'main-script',
                    'file1',
                ],
            ],
            ['sub-dir/file0'],
            ['sub-dir/file1'],
        ];

        yield [
            static function (): void {
                FS::mkdir('A');
                FS::touch('A/main-script');
                FS::touch('A/file0');
                FS::touch('A/file1');
            },
            [
                'main' => 'A/main-script',
                'directories' => [
                    'A',
                ],
                'directories-bin' => [
                    'A',
                ],
            ],
            ['A/file0', 'A/file1'],
            ['A/file0', 'A/file1'],
        ];

        yield [
            static function (): void {
                FS::mkdir('sub-dir');
                FS::mkdir('sub-dir/A');
                FS::touch('sub-dir/A/main-script');
                FS::touch('sub-dir/A/file0');
                FS::touch('sub-dir/A/file1');
            },
            [
                'base-path' => 'sub-dir',
                'main' => 'A/main-script',
                'directories' => [
                    'A',
                ],
                'directories-bin' => [
                    'A',
                ],
            ],
            ['sub-dir/A/file0', 'sub-dir/A/file1'],
            ['sub-dir/A/file0', 'sub-dir/A/file1'],
        ];

        yield [
            static function (): void {
                FS::mkdir('A');

                FS::touch('A/main-script');
                FS::touch('A/file0');
                FS::touch('A/file1');
            },
            [
                'main' => 'A/main-script',
                'finder' => [
                    [
                        'in' => [
                            'A',
                        ],
                    ],
                ],
                'finder-bin' => [
                    [
                        'in' => [
                            'A',
                        ],
                    ],
                ],
            ],
            ['A/file0', 'A/file1'],
            ['A/file0', 'A/file1'],
        ];

        yield [
            static function (): void {
                FS::touch('main-script');
                FS::touch('file0');
                FS::touch('file1');
            },
            [
                'main' => 'main-script',
                'finder' => [
                    [
                        'append' => [
                            'main-script',
                            'file0',
                        ],
                    ],
                ],
                'finder-bin' => [
                    [
                        'append' => [
                            'main-script',
                            'file1',
                        ],
                    ],
                ],
            ],
            ['file0'],
            ['file1'],
        ];

        yield [
            // https://github.com/humbug/box/issues/303
            // The main script is blacklisted but ensures this does not affect the other files collected, like here
            // the files found in a directory which has the same name as the main script
            static function (): void {
                FS::dumpFile('acme');
                FS::dumpFile('src/file00');
                FS::dumpFile('src/file10');
                FS::dumpFile('src/acme/file00');
                FS::dumpFile('src/acme/file10');
            },
            [
                'main' => 'acme',
                'directories' => ['src'],
            ],
            [
                'src/acme/file00',
                'src/acme/file10',
                'src/file00',
                'src/file10',
            ],
            [],
        ];
    }

    public static function configWithGeneratedArtefactProvider(): iterable
    {
        yield [
            static function (): void {
                FS::touch('acme.phar');
                FS::touch('index.php');
                FS::touch('file0');
                FS::touch('file1');
            },
            [
                'output' => 'acme.phar',
                'files' => [
                    'acme.phar',
                    'file0',
                ],
                'files-bin' => [
                    'acme.phar',
                    'file1',
                ],
            ],
            ['file0'],
            ['file1'],
        ];

        yield [
            static function (): void {
                FS::mkdir('sub-dir');

                FS::touch('sub-dir/acme.phar');
                FS::touch('sub-dir/index.php');
                FS::touch('sub-dir/file0');
                FS::touch('sub-dir/file1');
            },
            [
                'base-path' => 'sub-dir',
                'output' => 'acme.phar',
                'files' => [
                    'acme.phar',
                    'file0',
                ],
                'files-bin' => [
                    'acme.phar',
                    'file1',
                ],
            ],
            ['sub-dir/file0'],
            ['sub-dir/file1'],
        ];

        yield [
            static function (): void {
                FS::touch('index.php');
                FS::mkdir('A');
                FS::touch('A/acme.phar');
                FS::touch('A/file0');
                FS::touch('A/file1');
            },
            [
                'output' => 'A/acme.phar',
                'directories' => [
                    'A',
                ],
                'directories-bin' => [
                    'A',
                ],
            ],
            ['A/file0', 'A/file1'],
            ['A/file0', 'A/file1'],
        ];

        yield [
            static function (): void {
                FS::mkdir('sub-dir');
                FS::touch('sub-dir/index.php');
                FS::mkdir('sub-dir/A');
                FS::touch('sub-dir/A/acme.phar');
                FS::touch('sub-dir/A/file0');
                FS::touch('sub-dir/A/file1');
            },
            [
                'base-path' => 'sub-dir',
                'output' => 'A/acme.phar',
                'directories' => [
                    'A',
                ],
                'directories-bin' => [
                    'A',
                ],
            ],
            ['sub-dir/A/file0', 'sub-dir/A/file1'],
            ['sub-dir/A/file0', 'sub-dir/A/file1'],
        ];

        yield [
            static function (): void {
                FS::mkdir('A');

                FS::touch('index.php');
                FS::touch('A/acme.phar');
                FS::touch('A/file0');
                FS::touch('A/file1');
            },
            [
                'output' => 'A/acme.phar',
                'finder' => [
                    [
                        'in' => [
                            'A',
                        ],
                    ],
                ],
                'finder-bin' => [
                    [
                        'in' => [
                            'A',
                        ],
                    ],
                ],
            ],
            ['A/file0', 'A/file1'],
            ['A/file0', 'A/file1'],
        ];

        yield [
            static function (): void {
                FS::touch('acme.phar');
                FS::touch('index.php');
                FS::touch('file0');
                FS::touch('file1');
            },
            [
                'output' => 'acme.phar',
                'finder' => [
                    [
                        'append' => [
                            'acme.phar',
                            'file0',
                        ],
                    ],
                ],
                'finder-bin' => [
                    [
                        'append' => [
                            'acme.phar',
                            'file1',
                        ],
                    ],
                ],
            ],
            ['file0'],
            ['file1'],
        ];

        yield [
            // https://github.com/humbug/box/issues/303
            // The main script is blacklisted but ensures this does not affect the other files collected, like here
            // the files found in a directory which has the same name as the main script
            static function (): void {
                FS::dumpFile('index.php');
                FS::dumpFile('acme');
                FS::dumpFile('src/file00');
                FS::dumpFile('src/file10');
                FS::dumpFile('src/acme/file00');
                FS::dumpFile('src/acme/file10');
            },
            [
                'output' => 'acme',
                'directories' => ['src'],
            ],
            [
                'src/acme/file00',
                'src/acme/file10',
                'src/file00',
                'src/file10',
            ],
            [],
        ];
    }

    public static function jsonValidNonStringArrayProvider(): iterable
    {
        foreach (self::jsonPrimitivesProvider() as $key => $values) {
            if ('string' === $key) {
                continue;
            }

            yield $key.'[]' => [[$values]];
        }
    }

    public static function jsonValidNonObjectArrayProvider(): iterable
    {
        foreach (self::jsonPrimitivesProvider() as $key => $values) {
            if ('object' === $key) {
                continue;
            }

            yield $key.'[]' => [[$values]];
        }
    }

    public static function jsonPrimitivesProvider(): iterable
    {
        yield 'null' => null;
        yield 'bool' => true;
        yield 'number' => 30;
        yield 'string' => 'foo';
        yield 'object' => ['foo' => 'bar'];
        yield 'array' => ['foo', 'bar'];
    }

    public static function filesAutodiscoveryConfigProvider(): iterable
    {
        yield [
            static function (): void {},
            [],
            true,
        ];

        foreach ([true, false] as $booleanValue) {
            yield [
                static function (): void {},
                [
                    'force-autodiscovery' => $booleanValue,
                ],
                true,
            ];
        }

        foreach ([true, false] as $booleanValue) {
            yield [
                static function (): void {
                    FS::touch('main-script');
                    FS::touch('file0');
                    FS::touch('file-bin0');
                    FS::dumpFile('directory-bin0/file00');
                    FS::dumpFile('directory-bin1/file10');
                },
                [
                    'main' => 'main-script',
                    'files' => ['file0'],
                    'files-bin' => ['file-bin0'],
                    'directories-bin' => ['directory-bin0'],
                    'finder-bin' => [
                        [
                            'in' => ['directory-bin1'],
                        ],
                    ],
                    'force-autodiscovery' => $booleanValue,
                    'blacklist' => ['unknown'],
                ],
                true,
            ];
        }

        yield [
            static function (): void {
                FS::dumpFile('directory0/file00');
            },
            [
                'directories' => ['directory0'],
            ],
            false,
        ];

        yield [
            static function (): void {
                FS::dumpFile('directory0/file00');
            },
            [
                'directories' => ['directory0'],
                'force-autodiscovery' => true,
            ],
            true,
        ];

        yield [
            static function (): void {
                FS::dumpFile('directory1/file10');
            },
            [
                'finder' => [
                    [
                        'in' => ['directory1'],
                    ],
                ],
            ],
            false,
        ];

        yield [
            static function (): void {
                FS::dumpFile('directory1/file10');
            },
            [
                'finder' => [
                    [
                        'in' => ['directory1'],
                    ],
                ],
                'force-autodiscovery' => true,
            ],
            true,
        ];

        yield [
            static function (): void {
                FS::dumpFile('directory0/file00');
                FS::dumpFile('directory1/file10');
            },
            [
                'directories' => ['directory0'],
                'finder' => [
                    [
                        'in' => ['directory1'],
                    ],
                ],
            ],
            false,
        ];

        yield [
            static function (): void {
                FS::dumpFile('directory0/file00');
                FS::dumpFile('directory1/file10');
            },
            [
                'directories' => ['directory0'],
                'finder' => [
                    [
                        'in' => ['directory1'],
                    ],
                ],
                'force-autodiscovery' => true,
            ],
            true,
        ];
    }
}
