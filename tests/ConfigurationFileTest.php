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

use Generator;
use InvalidArgumentException;
use KevinGH\Box\Json\JsonValidationException;
use const DIRECTORY_SEPARATOR;
use function file_put_contents;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\make_path_absolute;
use function KevinGH\Box\FileSystem\rename;
use function KevinGH\Box\FileSystem\symlink;

/**
 * @covers \KevinGH\Box\Configuration
 */
class ConfigurationFileTest extends ConfigurationTestCase
{
    public function test_the_files_can_be_configured(): void
    {
        touch('file0');
        touch('file1');

        mkdir('B');
        touch('B/file1');
        touch('B/fileB0');
        touch('B/fileB1');
        touch('B/glob_finder_excluded_file');
        touch('B/glob-finder_excluded_file');

        mkdir('C');
        touch('C/fileC0');
        touch('C/fileC1');

        mkdir('D');
        touch('D/fileD0');
        touch('D/fileD1');
        touch('D/finder_excluded_file');

        mkdir('E');
        touch('E/fileE0');
        touch('E/fileE1');
        touch('E/finder_excluded_file');

        mkdir('F');
        touch('F/fileF0');
        touch('F/fileF1');
        touch('F/fileF2');
        touch('F/fileF3');

        mkdir('vendor');
        touch('vendor/glob_finder_excluded_file');
        touch('vendor/glob-finder_excluded_file');

        mkdir('vendor-bin');
        touch('vendor-bin/file1');

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

        $this->assertSame($expected, $actual);
        $this->assertCount(0, $this->config->getBinaryFiles());
    }

    /**
     * @dataProvider provideConfigWithMainScript
     */
    public function test_the_main_script_file_is_always_ignored(callable $setUp, array $config, array $expectedFiles, array $expectedBinFiles): void
    {
        $setUp();

        $this->setConfig($config);

        $actualFiles = $this->normalizePaths($this->config->getFiles());
        $actualBinFiles = $this->normalizePaths($this->config->getBinaryFiles());

        $this->assertSame($expectedFiles, $actualFiles);
        $this->assertSame($expectedBinFiles, $actualBinFiles);
    }

    public function test_configured_files_are_relative_to_base_path(): void
    {
        mkdir('sub-dir');

        rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        chdir('sub-dir');

        touch('file0');
        touch('file1');

        mkdir('B');
        touch('B/fileB0');
        touch('B/fileB1');
        touch('B/glob_finder_excluded_file');
        touch('B/glob-finder_excluded_file');

        mkdir('C');
        touch('C/fileC0');
        touch('C/fileC1');

        mkdir('D');
        touch('D/fileD0');
        touch('D/fileD1');
        touch('D/finder_excluded_file');

        mkdir('E');
        touch('E/fileE0');
        touch('E/fileE1');
        touch('E/finder_excluded_file');

        mkdir('vendor');
        touch('vendor/glob_finder_excluded_file');
        touch('vendor/glob-finder_excluded_file');

        mkdir('vendor-bin');
        touch('vendor-bin/file1');

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

        $this->assertSame($expected, $actual);
        $this->assertCount(0, $this->config->getBinaryFiles());
    }

    public function test_configured_files_are_relative_to_base_path_unless_they_are_absolute_paths(): void
    {
        mkdir('sub-dir');
        chdir('sub-dir');

        touch('file0');
        touch('file1');

        mkdir('B');
        touch('B/fileB0');
        touch('B/fileB1');

        mkdir('C');
        touch('C/fileC0');
        touch('C/fileC1');

        mkdir('D');
        touch('D/fileD0');
        touch('D/fileD1');
        touch('D/finder_excluded_file');

        mkdir('E');
        touch('E/fileE0');
        touch('E/fileE1');
        touch('E/finder_excluded_file');

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

        $this->assertSame($expected, $actual);
        $this->assertCount(0, $this->config->getBinaryFiles());
    }

    public function test_the_files_belonging_to_dev_packages_are_ignored_only_in_the_finder_config(): void
    {
        dump_file('composer.json', '{}');
        dump_file(
            'composer.lock',
            <<<'JSON'
{
    "packages-dev": [
        {"name": "acme/foo"},
        {"name": "acme/bar"},
        {"name": "acme/oof"}
    ]
}
JSON
);

        touch('file0');
        touch('file1');

        dump_file('vendor/acme/foo/af0');
        dump_file('vendor/acme/foo/af1');

        dump_file('vendor/acme/bar/ab0');
        dump_file('vendor/acme/bar/ab1');

        dump_file('vendor/acme/oof/ao0');
        dump_file('vendor/acme/oof/ao1');

        mkdir('C');
        touch('C/fileC0');
        touch('C/fileC1');

        mkdir('D');
        touch('D/fileD0');
        touch('D/fileD1');
        touch('D/finder_excluded_file');

        mkdir('E');
        touch('E/fileE0');
        touch('E/fileE1');
        touch('E/finder_excluded_file');

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
        ];

        $actual = $this->normalizePaths($this->config->getFiles());

        $this->assertSame($expected, $actual);
        $this->assertCount(0, $this->config->getBinaryFiles());
    }

    public function test_a_non_existent_file_cannot_be_added_to_the_list_of_files(): void
    {
        try {
            $this->setConfig([
                'files' => [
                    'non-existent',
                ],
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $filePath = make_path_absolute('non-existent', $this->tmp);

            $this->assertSame(
                sprintf(
                    '"files" must contain a list of existing files. Could not find "%s".',
                    $filePath
                ),
                $exception->getMessage()
            );
        }
    }

    public function test_symlinks_are_not_supported_in_finder_in_setting(): void
    {
        mkdir('sub-dir');

        rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        mkdir('F');
        touch('F/fileF0');
        touch('F/fileF1');
        touch('F/finder_excluded_file');

        symlink('F', 'sub-dir/F');

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

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $link = $this->tmp.'/sub-dir/F';

            $this->assertSame(
                "Cannot append the link \"$link\" to the Finder: links are not supported.",
                $exception->getMessage()
            );
        }
    }

    public function test_appending_a_file_from_a_symlinked_directory_is_not_supported(): void
    {
        mkdir('sub-dir');

        rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        mkdir('F');
        touch('F/fileF0');
        touch('F/fileF1');
        touch('F/finder_excluded_file');

        symlink('F', 'sub-dir/F');

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

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $link = $this->tmp.'/sub-dir/F/fileF0';

            $this->assertSame(
                "Path \"$link\" was expected to be a file or directory. It may be a symlink (which are unsupported).",
                $exception->getMessage()
            );
        }
    }

    public function test_appending_a_symlinked_file_is_not_supported(): void
    {
        mkdir('sub-dir');

        rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        mkdir('F');
        touch('F/fileF0');
        touch('F/fileF1');
        touch('F/finder_excluded_file');

        symlink('F/fileF0', 'sub-dir/F/fileF0');

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

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $link = $this->tmp.'/sub-dir/F/fileF0';

            $this->assertSame(
                "Cannot append the link \"$link\" to the Finder: links are not supported.",
                $exception->getMessage()
            );
        }
    }

    public function test_configuring_a_symlink_file_is_not_supported(): void
    {
        mkdir('sub-dir');

        rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        mkdir('F');
        touch('F/fileF0');
        touch('F/fileF1');
        touch('F/finder_excluded_file');

        symlink('F/fileF0', 'sub-dir/F/fileF0');

        try {
            $this->setConfig([
                'base-path' => 'sub-dir',
                'files' => [
                    'F/fileF0',
                ],
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $link = $this->tmp.'/sub-dir/F/fileF0';

            $this->assertSame(
                "Cannot add the link \"$link\": links are not supported.",
                $exception->getMessage()
            );
        }
    }

    public function test_configuring_a_symlink_directory_is_not_supported(): void
    {
        mkdir('sub-dir');

        rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        mkdir('F');
        touch('F/fileF0');
        touch('F/fileF1');
        touch('F/finder_excluded_file');

        symlink('F', 'sub-dir/F');

        try {
            $this->setConfig([
                'base-path' => 'sub-dir',
                'directories' => [
                    'F',
                ],
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $link = $this->tmp.'/sub-dir/F';

            $this->assertSame(
                "Cannot add the link \"$link\": links are not supported.",
                $exception->getMessage()
            );
        }
    }

    public function test_cannot_add_a_directory_to_the_list_of_files(): void
    {
        mkdir('dirA');

        try {
            $this->setConfig([
                'files' => [
                    'dirA',
                ],
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $filePath = $this->tmp.DIRECTORY_SEPARATOR.'dirA';

            $this->assertSame(
                sprintf(
                    '"files" must contain a list of existing files. Could not find "%s".',
                    $filePath
                ),
                $exception->getMessage()
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

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $dirPath = $this->tmp.DIRECTORY_SEPARATOR.'non-existent';

            $this->assertSame(
                sprintf(
                    '"directories" must contain a list of existing directories. Could not find "%s".',
                    $dirPath
                ),
                $exception->getMessage()
            );
        }
    }

    public function test_cannot_add_a_file_to_the_list_of_directories(): void
    {
        touch('foo');

        try {
            $this->setConfig([
                'directories' => [
                    'foo',
                ],
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $dirPath = $this->tmp.DIRECTORY_SEPARATOR.'foo';

            $this->assertSame(
                sprintf(
                    '"directories" must contain a list of existing directories. Could not find "%s".',
                    $dirPath
                ),
                $exception->getMessage()
            );
        }
    }

    public function test_the_bin_files_iterator_can_be_configured(): void
    {
        touch('file0');
        touch('file1');

        mkdir('B');
        touch('B/fileB0');
        touch('B/fileB1');

        mkdir('C');
        touch('C/fileC0');
        touch('C/fileC1');

        mkdir('D');
        touch('D/fileD0');
        touch('D/fileD1');
        touch('D/finder_excluded_file');

        mkdir('E');
        touch('E/fileE0');
        touch('E/fileE1');
        touch('E/finder_excluded_file');

        mkdir('F');
        touch('F/fileF0');
        touch('F/fileF1');

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

        $this->assertSame($expected, $actual);
        $this->assertCount(0, $this->config->getFiles());
    }

    public function test_configured_bin_files_are_relative_to_base_path(): void
    {
        mkdir('sub-dir');

        rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        chdir('sub-dir');

        touch('file0');
        touch('file1');

        mkdir('B');
        touch('B/fileB0');
        touch('B/fileB1');

        mkdir('C');
        touch('C/fileC0');
        touch('C/fileC1');

        mkdir('D');
        touch('D/fileD0');
        touch('D/fileD1');
        touch('D/finder_excluded_file');

        mkdir('E');
        touch('E/fileE0');
        touch('E/fileE1');
        touch('E/finder_excluded_file');

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

        $this->assertSame($expected, $actual);
        $this->assertCount(0, $this->config->getFiles());
    }

    public function test_configured_bin_files_are_relative_to_base_path_unless_they_are_absolute_paths(): void
    {
        mkdir('sub-dir');
        chdir('sub-dir');

        touch('file0');
        touch('file1');

        mkdir('B');
        touch('B/fileB0');
        touch('B/fileB1');

        mkdir('C');
        touch('C/fileC0');
        touch('C/fileC1');

        mkdir('D');
        touch('D/fileD0');
        touch('D/fileD1');
        touch('D/finder_excluded_file');

        mkdir('E');
        touch('E/fileE0');
        touch('E/fileE1');
        touch('E/finder_excluded_file');

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

        $this->assertSame($expected, $actual);
        $this->assertCount(0, $this->config->getFiles());
    }

    public function test_cannot_add_a_non_existent_bin_file_to_the_list_of_files(): void
    {
        try {
            $this->setConfig([
                'files-bin' => [
                    'non-existent',
                ],
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $filePath = $this->tmp.DIRECTORY_SEPARATOR.'non-existent';

            $this->assertSame(
                sprintf(
                    '"files-bin" must contain a list of existing files. Could not find "%s".',
                    $filePath
                ),
                $exception->getMessage()
            );
        }
    }

    public function test_cannot_add_a_directory_to_the_list_of_bin_files(): void
    {
        mkdir('dirA');

        try {
            $this->setConfig([
                'files-bin' => [
                    'dirA',
                ],
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $filePath = $this->tmp.DIRECTORY_SEPARATOR.'dirA';

            $this->assertSame(
                sprintf(
                    '"files-bin" must contain a list of existing files. Could not find "%s".',
                    $filePath
                ),
                $exception->getMessage()
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

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $dirPath = $this->tmp.DIRECTORY_SEPARATOR.'non-existent';

            $this->assertSame(
                sprintf(
                    '"directories-bin" must contain a list of existing directories. Could not find "%s".',
                    $dirPath
                ),
                $exception->getMessage()
            );
        }
    }

    public function test_cannot_add_a_file_to_the_list_of_bin_directories(): void
    {
        touch('foo');

        try {
            $this->setConfig([
                'directories-bin' => [
                    'foo',
                ],
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $dirPath = $this->tmp.DIRECTORY_SEPARATOR.'foo';

            $this->assertSame(
                sprintf(
                    '"directories-bin" must contain a list of existing directories. Could not find "%s".',
                    $dirPath
                ),
                $exception->getMessage()
            );
        }
    }

    public function test_the_cannot_be_included_twice(): void
    {
        mkdir('A');
        touch('A/foo');

        mkdir('B');
        touch('B/bar');

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

        $this->assertSame(
            $expected,
            $this->normalizePaths($this->config->getFiles())
        );
        $this->assertSame(
            $expected,
            $this->normalizePaths($this->config->getBinaryFiles())
        );
    }

    /**
     * @dataProvider provideJsonValidNonStringArray
     *
     * @param mixed $value
     */
    public function test_blacklist_value_must_be_an_array_of_strings($value): void
    {
        try {
            $this->setConfig([
                'blacklist' => $value,
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (JsonValidationException $exception) {
            $this->assertRegExp(
                '/does not match the expected JSON schema:/',
                $exception->getMessage()
            );
        }
    }

    public function test_the_blacklist_input_is_normalized(): void
    {
        mkdir('B');
        touch('B/fileB0');
        touch('B/fileB1');

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

        $this->assertSame($expected, $actual);
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

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideJsonValidNonStringArray
     *
     * @param mixed $value
     */
    public function test_files_value_must_be_an_array_of_strings($value): void
    {
        try {
            $this->setConfig([
                'files' => $value,
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (JsonValidationException $exception) {
            $this->assertRegExp(
                '/does not match the expected JSON schema:/',
                $exception->getMessage()
            );
        }
    }

    /**
     * @dataProvider provideJsonValidNonStringArray
     *
     * @param mixed $value
     */
    public function test_bin_files_value_must_be_an_array_of_strings($value): void
    {
        try {
            $this->setConfig([
                'files-bin' => $value,
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (JsonValidationException $exception) {
            $this->assertRegExp(
                '/does not match the expected JSON schema:/',
                $exception->getMessage()
            );
        }
    }

    public function test_the_files_and_bin_files_input_is_normalized(): void
    {
        touch('foo');

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

        $this->assertSame(
            $expected,
            $this->normalizePaths($this->config->getFiles())
        );
        $this->assertSame(
            $expected,
            $this->normalizePaths($this->config->getBinaryFiles())
        );
    }

    /**
     * @dataProvider provideJsonValidNonStringArray
     *
     * @param mixed $value
     */
    public function test_directories_value_must_be_an_array_of_strings($value): void
    {
        try {
            $this->setConfig([
                'directories' => $value,
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (JsonValidationException $exception) {
            $this->assertRegExp(
                '/does not match the expected JSON schema:/',
                $exception->getMessage()
            );
        }
    }

    /**
     * @dataProvider provideJsonValidNonStringArray
     *
     * @param mixed $value
     */
    public function test_bin_directories_value_must_be_an_array_of_strings($value): void
    {
        try {
            $this->setConfig([
                'directories-bin' => $value,
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (JsonValidationException $exception) {
            $this->assertRegExp(
                '/does not match the expected JSON schema:/',
                $exception->getMessage()
            );
        }
    }

    public function test_the_directories_and_bin_directories_input_is_normalized(): void
    {
        mkdir('A');
        touch('A/foo');

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

        $this->assertSame(
            $expected,
            $this->normalizePaths($this->config->getFiles())
        );
        $this->assertSame(
            $expected,
            $this->normalizePaths($this->config->getBinaryFiles())
        );
    }

    /**
     * @dataProvider provideJsonValidNonObjectArray
     *
     * @param mixed $value
     */
    public function test_finder_value_must_be_an_array_of_objects($value): void
    {
        try {
            $this->setConfig([
                'finder' => $value,
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (JsonValidationException $exception) {
            $this->assertRegExp(
                '/does not match the expected JSON schema:/',
                $exception->getMessage()
            );
        }
    }

    public function test_finder_and_bin_finder_input_is_normalized(): void
    {
        mkdir('sub-dir');

        rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        chdir('sub-dir');

        mkdir('A');
        touch('A/foo');

        mkdir('A/D0');
        touch('A/D0/da0');

        mkdir('A/D1');
        touch('A/D1/da1');

        mkdir('B');
        touch('B/bar');

        mkdir('D');
        touch('D/doo');

        mkdir('D/D0');
        touch('D/D0/d0o');

        mkdir('D/D1');
        touch('D/D1/d1o');

        touch('oof');
        touch('rab');

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

        $this->assertEquals(
            $expected,
            $this->normalizePaths($this->config->getFiles()),
            '',
            .0,
            10,
            true
        );
        $this->assertEquals(
            $expected,
            $this->normalizePaths($this->config->getBinaryFiles()),
            '',
            .0,
            10,
            true
        );
    }

    public function test_finder_and_bin_finder_exclude_files_or_directories_may_not_exists(): void
    {
        mkdir('A');
        touch('A/foo');

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

        $this->assertEquals(
            $expected,
            $this->normalizePaths($this->config->getFiles()),
            '',
            .0,
            10,
            true
        );
        $this->assertEquals(
            $expected,
            $this->normalizePaths($this->config->getBinaryFiles()),
            '',
            .0,
            10,
            true
        );
    }

    public function test_finder_array_arguments_are_called_as_single_arguments(): void
    {
        mkdir('A');
        touch('A/foo');

        mkdir('B');
        touch('B/bar');

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

        $this->assertSame($expected, $actual);
    }

    public function test_the_finder_config_cannot_include_invalid_methods(): void
    {
        try {
            $this->setConfig([
                'finder' => [
                    ['invalidMethod' => 'whargarbl'],
                ],
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The method "Finder::invalidMethod" does not exist.',
                $exception->getMessage()
            );
        }
    }

    public function test_the_composer_json_and_lock_files_are_always_included_even_when_the_user_configure_which_files_to_pick(): void
    {
        touch('file0');
        touch('file1');

        mkdir('B');
        touch('B/fileB0');
        touch('B/fileB1');

        file_put_contents('composer.json', '{}');
        file_put_contents('composer.lock', '{}');

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

        $this->assertSame($expected, $actual);
        $this->assertCount(0, $this->config->getBinaryFiles());

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

        $this->assertSame($expected, $actual);
        $this->assertCount(0, $this->config->getBinaryFiles());
    }

    public function provideConfigWithMainScript()
    {
        yield [
            function (): void {
                touch('main-script');
                touch('file0');
                touch('file1');
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
            function (): void {
                mkdir('sub-dir');

                touch('sub-dir/main-script');
                touch('sub-dir/file0');
                touch('sub-dir/file1');
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
            function (): void {
                mkdir('A');
                touch('A/main-script');
                touch('A/file0');
                touch('A/file1');
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
            function (): void {
                mkdir('sub-dir');
                mkdir('sub-dir/A');
                touch('sub-dir/A/main-script');
                touch('sub-dir/A/file0');
                touch('sub-dir/A/file1');
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
            function (): void {
                mkdir('A');

                touch('A/main-script');
                touch('A/file0');
                touch('A/file1');
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
            function (): void {
                touch('main-script');
                touch('file0');
                touch('file1');
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
    }

    public function provideJsonValidNonStringArray(): Generator
    {
        foreach ($this->provideJsonPrimitives() as $key => $values) {
            if ('string' === $key) {
                continue;
            }

            yield $key.'[]' => [[$values]];
        }
    }

    public function provideJsonValidNonObjectArray()
    {
        foreach ($this->provideJsonPrimitives() as $key => $values) {
            if ('object' === $key) {
                continue;
            }

            yield $key.'[]' => [[$values]];
        }
    }

    public function provideJsonPrimitives(): Generator
    {
        yield 'null' => null;
        yield 'bool' => true;
        yield 'number' => 30;
        yield 'string' => 'foo';
        yield 'object' => ['foo' => 'bar'];
        yield 'array' => ['foo', 'bar'];
    }
}
