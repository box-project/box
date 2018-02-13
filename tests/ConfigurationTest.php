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

use Closure;
use Generator;
use Herrera\Annotations\Tokenizer;
use InvalidArgumentException;
use KevinGH\Box\Compactor\DummyCompactor;
use KevinGH\Box\Compactor\InvalidCompactor;
use KevinGH\Box\Compactor\Php;
use KevinGH\Box\Console\ConfigurationHelper;
use KevinGH\Box\Json\JsonValidationException;
use KevinGH\Box\Test\FileSystemTestCase;
use Phar;
use SplFileInfo;
use stdClass;
use Symfony\Component\Finder\Finder;
use function iter\fn\method;
use function KevinGH\Box\FileSystem\make_path_absolute;

/**
 * @covers \KevinGH\Box\Configuration
 */
class ConfigurationTest extends FileSystemTestCase
{
    /**
     * @var Configuration
     */
    private $config;

    /**
     * @var string
     */
    private $file;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->file = make_path_absolute('box.json', $this->tmp);
        touch($this->file);

        $this->config = Configuration::create($this->file, (object) []);
    }

    public function test_a_default_alias_is_provided_when_non_has_been_configured(): void
    {
        $this->assertSame('default.phar', $this->config->getAlias());
    }

    public function test_the_alias_can_be_configured(): void
    {
        $this->setConfig(['alias' => 'test.phar']);

        $this->assertSame('test.phar', $this->config->getAlias());
    }

    public function test_the_alias_value_is_normalized(): void
    {
        $this->setConfig(['alias' => '  test.phar  ']);

        $this->assertSame('test.phar', $this->config->getAlias());
    }

    public function test_the_alias_cannot_be_empty(): void
    {
        try {
            $this->setConfig(['alias' => '']);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'A PHAR alias cannot be empty.',
                $exception->getMessage()
            );
        }
    }

    public function test_the_alias_must_be_a_string(): void
    {
        try {
            $this->setConfig(['alias' => true]);

            $this->fail('Expected exception to be thrown.');
        } catch (JsonValidationException $exception) {
            $this->assertSame(
                <<<EOF
"$this->file" does not match the expected JSON schema:
  - alias : Boolean value found, but a string is required
EOF
                ,
                $exception->getMessage()
            );
        }
    }

    public function test_the_default_base_path_used_is_the_current_working_directory(): void
    {
        $this->assertSame($this->tmp, $this->config->getBasePath());
    }

    public function test_configure_the_base_path(): void
    {
        mkdir($this->tmp.DIRECTORY_SEPARATOR.'test');

        $this->setConfig(
            [
                'base-path' => $this->tmp.DIRECTORY_SEPARATOR.'test',
            ]
        );

        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test',
            $this->config->getBasePath()
        );
    }

    public function test_it_cannot_use_a_non_existent_directory_as_abase_path(): void
    {
        try {
            $this->setConfig(
                [
                    'base-path' => $this->tmp.DIRECTORY_SEPARATOR.'test',
                ]
            );

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The base path "'.$this->tmp.DIRECTORY_SEPARATOR.'test" is not a directory or does not exist.',
                $exception->getMessage()
            );
        }
    }

    public function test_it_cannot_a_path_as_a_base_path(): void
    {
        touch('foo');

        try {
            $this->setConfig(
                [
                    'base-path' => $this->tmp.DIRECTORY_SEPARATOR.'foo',
                ]
            );

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The base path "'.$this->tmp.DIRECTORY_SEPARATOR.'foo" is not a directory or does not exist.',
                $exception->getMessage()
            );
        }
    }

    public function test_if_the_base_path_is_relative_then_it_is_relative_to_the_current_working_directory(): void
    {
        mkdir('dir');

        $this->setConfig(
            [
                'base-path' => 'dir',
            ]
        );

        $expected = $this->tmp.DIRECTORY_SEPARATOR.'dir';

        $this->assertSame($expected, $this->config->getBasePath());
    }

    public function test_the_base_path_value_is_normalized(): void
    {
        mkdir('dir');

        $this->setConfig(
            [
                'base-path' => ' dir ',
            ]
        );

        $expected = $this->tmp.DIRECTORY_SEPARATOR.'dir';

        $this->assertSame($expected, $this->config->getBasePath());
    }

    /**
     * @dataProvider provideJsonValidNonStringValues
     *
     * @param mixed $value
     */
    public function test_the_base_path_value_must_be_a_string($value): void
    {
        try {
            $this->setConfig(
                [
                    'base-path' => $value,
                ]
            );

            $this->fail('Expected exception to be thrown.');
        } catch (JsonValidationException $exception) {
            $this->assertRegExp(
                '/does not match the expected JSON schema:/',
                $exception->getMessage()
            );
        }
    }

    public function test_it_can_provide_the_relative_path_relative_to_the_config_base_path(): void
    {
        $fullPath = $this->config->getBasePath().DIRECTORY_SEPARATOR.'test';

        $expected = 'test';
        $actual = $this->config->getBasePathRetriever()($fullPath);

        $this->assertSame($expected, $actual);
    }

    public function test_there_is_no_file_configured_by_default(): void
    {
        $this->assertCount(0, $this->config->getFiles());
    }

    public function test_configure_the_files_iterator(): void
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

        $this->setConfig(
            [
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
                ],
            ]
        );

        // Relative to the current working directory for readability
        $expected = [
            'file0',
            'file1',    // 'files' & 'files-bin' are not affected by the blacklist filter
            'B/fileB0',
            'C/fileC0',
            'D/fileD0',
            'E/fileE0',
        ];

        $actual = $this->normalizeConfigPaths($this->config->getFiles());

        $this->assertSame($expected, $actual);
        $this->assertCount(0, $this->config->getBinaryFiles());
    }

    public function test_configured_files_are_relative_to_base_path(): void
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

        $this->setConfig(
            [
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
                ],
            ]
        );

        // Relative to the current working directory for readability
        $expected = [
            'sub-dir/file0',
            'sub-dir/file1',
            'sub-dir/B/fileB0',
            'sub-dir/C/fileC0',
            'sub-dir/D/fileD0',
            'sub-dir/E/fileE0',
        ];

        $actual = $this->normalizeConfigPaths($this->config->getFiles());

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

        $this->setConfig(
            [
                'files' => [
                    $basePath.'file0',
                    $basePath.'file1',
                ],
                'directories' => [
                    $basePath.'B',
                    $basePath.'C',
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
            ]
        );

        // Relative to the current working directory for readability
        $expected = [
            'sub-dir/file0',
            'sub-dir/file1',
            'sub-dir/B/fileB0',
            'sub-dir/C/fileC0',
            'sub-dir/D/fileD0',
            'sub-dir/E/fileE0',
        ];

        $actual = $this->normalizeConfigPaths($this->config->getFiles());

        $this->assertSame($expected, $actual);
        $this->assertCount(0, $this->config->getBinaryFiles());
    }

    public function test_cannot_add_a_non_existent_file_to_the_list_of_files(): void
    {
        try {
            $this->setConfig(
                [
                    'files' => [
                        'non-existent',
                    ],
                ]
            );

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

    public function test_cannot_add_a_directory_to_the_list_of_files(): void
    {
        mkdir('dirA');

        try {
            $this->setConfig(
                [
                    'files' => [
                        'dirA',
                    ],
                ]
            );

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
            $this->setConfig(
                [
                    'directories' => [
                        'non-existent',
                    ],
                ]
            );

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
            $this->setConfig(
                [
                    'directories' => [
                        'foo',
                    ],
                ]
            );

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

    public function test_configure_the_bin_files_iterator(): void
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

        $this->setConfig(
            [
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
            ]
        );

        // Relative to the current working directory for readability
        $expected = [
            'file0',
            'file1',    // 'files' & 'files-bin' are not affected by the blacklist filter
            'B/fileB0',
            'C/fileC0',
            'D/fileD0',
            'E/fileE0',
        ];

        $actual = $this->normalizeConfigPaths($this->config->getBinaryFiles());

        $this->assertSame($expected, $actual);
        $this->assertCount(0, $this->config->getFiles());
    }

    public function test_configured_bin_files_are_relative_to_base_path(): void
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

        $this->setConfig(
            [
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
            ]
        );

        // Relative to the current working directory for readability
        $expected = [
            'sub-dir/file0',
            'sub-dir/file1',
            'sub-dir/B/fileB0',
            'sub-dir/C/fileC0',
            'sub-dir/D/fileD0',
            'sub-dir/E/fileE0',
        ];

        $actual = $this->normalizeConfigPaths($this->config->getBinaryFiles());

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

        $this->setConfig(
            [
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
            ]
        );

        // Relative to the current working directory for readability
        $expected = [
            'sub-dir/file0',
            'sub-dir/file1',
            'sub-dir/B/fileB0',
            'sub-dir/C/fileC0',
            'sub-dir/D/fileD0',
            'sub-dir/E/fileE0',
        ];

        $actual = $this->normalizeConfigPaths($this->config->getBinaryFiles());

        $this->assertSame($expected, $actual);
        $this->assertCount(0, $this->config->getFiles());
    }

    public function test_cannot_add_a_non_existent_bin_file_to_the_list_of_files(): void
    {
        try {
            $this->setConfig(
                [
                    'files-bin' => [
                        'non-existent',
                    ],
                ]
            );

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
            $this->setConfig(
                [
                    'files-bin' => [
                        'dirA',
                    ],
                ]
            );

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
            $this->setConfig(
                [
                    'directories-bin' => [
                        'non-existent',
                    ],
                ]
            );

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
            $this->setConfig(
                [
                    'directories-bin' => [
                        'foo',
                    ],
                ]
            );

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

        $this->setConfig(
            [
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
            ]
        );

        // Relative to the current working directory for readability
        $expected = [
            'A/foo',
            'B/bar',
        ];

        $this->assertSame(
            $expected,
            $this->normalizeConfigPaths($this->config->getFiles())
        );
        $this->assertSame(
            $expected,
            $this->normalizeConfigPaths($this->config->getBinaryFiles())
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
            $this->setConfig(
                [
                    'blacklist' => $value,
                ]
            );

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

        $this->setConfig(
            [
                'directories' => [
                    'B',
                ],
                'blacklist' => [
                    ' B/fileB1 ',
                ],
            ]
        );

        // Relative to the current working directory for readability
        $expected = [
            'B/fileB0',
        ];
        $actual = $this->normalizeConfigPaths($this->config->getFiles());

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
            $this->setConfig(
                [
                    'files' => $value,
                ]
            );

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
            $this->setConfig(
                [
                    'files-bin' => $value,
                ]
            );

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

        $this->setConfig(
            [
                'files' => [
                    ' foo ',
                ],
                'files-bin' => [
                    ' foo ',
                ],
            ]
        );

        // Relative to the current working directory for readability
        $expected = ['foo'];

        $this->assertSame(
            $expected,
            $this->normalizeConfigPaths($this->config->getFiles())
        );
        $this->assertSame(
            $expected,
            $this->normalizeConfigPaths($this->config->getBinaryFiles())
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
            $this->setConfig(
                [
                    'directories' => $value,
                ]
            );

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
            $this->setConfig(
                [
                    'directories-bin' => $value,
                ]
            );

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

        $this->setConfig(
            [
                'directories' => [
                    ' A ',
                ],
                'directories-bin' => [
                    ' A ',
                ],
            ]
        );

        // Relative to the current working directory for readability
        $expected = ['A/foo'];

        $this->assertSame(
            $expected,
            $this->normalizeConfigPaths($this->config->getFiles())
        );
        $this->assertSame(
            $expected,
            $this->normalizeConfigPaths($this->config->getBinaryFiles())
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
            $this->setConfig(
                [
                    'finder' => $value,
                ]
            );

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

        $this->setConfig(
            [
                'base-path' => 'sub-dir',
                'finder' => $finderConfig,
                'finder-bin' => $finderConfig,
            ]
        );

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
            $this->normalizeConfigPaths($this->config->getFiles()),
            '',
            .0,
            10,
            true
        );
        $this->assertEquals(
            $expected,
            $this->normalizeConfigPaths($this->config->getBinaryFiles()),
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

        $this->setConfig(
            [
                'finder' => $finderConfig,
                'finder-bin' => $finderConfig,
            ]
        );

        // Relative to the current working directory for readability
        $expected = ['A/foo'];

        $this->assertEquals(
            $expected,
            $this->normalizeConfigPaths($this->config->getFiles()),
            '',
            .0,
            10,
            true
        );
        $this->assertEquals(
            $expected,
            $this->normalizeConfigPaths($this->config->getBinaryFiles()),
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

        $this->setConfig(
            [
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
            ]
        );

        // Relative to the current working directory for readability
        $expected = [
            'A/foo',
            'B/bar',
        ];
        $actual = $this->normalizeConfigPaths($this->config->getFiles());

        $this->assertSame($expected, $actual);
    }

    public function test_the_finder_config_cannot_include_invalid_methods(): void
    {
        try {
            $this->setConfig(
                ['finder' => [['invalidMethod' => 'whargarbl']]]
            );

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The method "Finder::invalidMethod" does not exist.',
                $exception->getMessage()
            );
        }
    }

    public function test_get_the_bootstrap_file(): void
    {
        $this->assertNull($this->config->getBootstrapFile());
    }

    public function test_configure_the_bootstrap_file(): void
    {
        touch('test.php');

        $this->setconfig(['bootstrap' => 'test.php']);

        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test.php',
            $this->config->getBootstrapFile()
        );
    }

    public function test_get_the_compactors(): void
    {
        $this->assertSame([], $this->config->getCompactors());
    }

    public function test_configure_the_compactors(): void
    {
        $this->setConfig(
            [
                'compactors' => [
                    Php::class,
                    DummyCompactor::class,
                ],
            ]
        );

        $compactors = $this->config->getCompactors();

        $this->assertInstanceOf(Php::class, $compactors[0]);
        $this->assertInstanceOf(DummyCompactor::class, $compactors[1]);
    }

    public function test_it_cannot_get_the_compactors_with_an_invalid_class(): void
    {
        try {
            $this->setConfig(['compactors' => ['NoSuchClass']]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The compactor class "NoSuchClass" does not exist.',
                $exception->getMessage()
            );
        }
    }

    public function test_it_cannot_configure_an_invalid_compactor(): void
    {
        try {
            $this->setConfig(['compactors' => [InvalidCompactor::class]]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                sprintf(
                    'The class "%s" is not a compactor class.',
                    InvalidCompactor::class
                ),
                $exception->getMessage()
            );
        }
    }

    public function test_get_compactors_annotations(): void
    {
        $this->setConfig(
            [
                'annotations' => (object) [
                    'ignore' => [
                        'author',
                    ],
                ],
                'compactors' => [
                    Php::class,
                ],
            ]
        );

        $compactors = $this->config->getCompactors();

        $tokenizer = (
            Closure::bind(
                function (Php $phpCompactor): Tokenizer {
                    return $phpCompactor->tokenizer;
                },
                null,
                Php::class
            )
        )($compactors[0]);

        $this->assertNotNull($tokenizer);

        $ignored = (
            Closure::bind(
                function (Tokenizer $tokenizer): array {
                    return $tokenizer->ignored;
                },
                null,
                Tokenizer::class
            )
        )($tokenizer);

        $this->assertSame(['author'], $ignored);
    }

    public function test_get_compression_algorithm(): void
    {
        $this->assertNull($this->config->getCompressionAlgorithm());
    }

    public function test_configure_compression_algorithm(): void
    {
        $this->setConfig(['compression' => Phar::BZ2]);

        $this->assertSame(Phar::BZ2, $this->config->getCompressionAlgorithm());
    }

    public function test_configure_compression_algorithm_with_a_string(): void
    {
        $this->setConfig(['compression' => 'BZ2']);

        $this->assertSame(Phar::BZ2, $this->config->getCompressionAlgorithm());
    }

    /**
     * @dataProvider provideInvalidCompressionAlgorithms
     *
     * @param mixed $compression
     */
    public function test_configure_compression_algorithm_with_an_invalid_algorithm($compression, string $errorMessage): void
    {
        try {
            $this->setConfig(['compression' => $compression]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                $errorMessage,
                $exception->getMessage()
            );
        } catch (JsonValidationException $exception) {
            $this->assertRegExp(
                '/does not match the expected JSON schema:/',
                $exception->getMessage()
            );
        }
    }

    public function test_get_file_mode(): void
    {
        $this->assertNull($this->config->getFileMode());
    }

    public function test_configure_file_mode(): void
    {
        $this->setConfig(['chmod' => '0755']);

        $this->assertSame(0755, $this->config->getFileMode());
    }

    public function test_get_main_script_path(): void
    {
        $this->assertNull($this->config->getMainScriptPath());
    }

    public function test_configure_main_script(): void
    {
        touch('test.php');

        $this->setConfig(['main' => 'test.php']);

        $this->assertSame('test.php', $this->config->getMainScriptPath());
    }

    public function test_get_main_script_content(): void
    {
        $this->assertNull($this->config->getMainScriptContent());
    }

    public function test_configure_main_script_content(): void
    {
        file_put_contents('test.php', 'script content');

        $this->setConfig(['main' => 'test.php']);

        $this->assertSame('script content', $this->config->getMainScriptContent());
    }

    public function test_main_script_content_ignores_shebang_line(): void
    {
        file_put_contents('test.php', "#!/usr/bin/env php\ntest");

        $this->setConfig(['main' => 'test.php']);

        $this->assertSame('test', $this->config->getMainScriptContent());
    }

    public function test_it_cannot_get_the_main_script_if_file_doesnt_exists(): void
    {
        try {
            $this->setConfig(['main' => 'test.php']);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                "File \"{$this->tmp}/test.php\" was expected to exist.",
                $exception->getMessage()
            );
        }
    }

    public function test_get_map(): void
    {
        $this->assertSame([], $this->config->getMap());
    }

    public function test_configure_map(): void
    {
        $this->setConfig(
            [
                'map' => [
                    ['a' => 'b'],
                    ['_empty_' => 'c'],
                ],
            ]
        );

        $this->assertSame(
            [
                ['a' => 'b'],
                ['' => 'c'],
            ],
            $this->config->getMap()
        );
    }

    public function test_get_mapper(): void
    {
        $this->setConfig(
            [
                'map' => [
                    ['first/test/path' => 'a'],
                    ['' => 'b/'],
                ],
            ]
        );

        $mapFile = $this->config->getFileMapper();

        $this->assertSame(
            'a/sub/path/file.php',
            $mapFile('first/test/path/sub/path/file.php')
        );

        $this->assertSame(
            'b/second/test/path/sub/path/file.php',
            $mapFile('second/test/path/sub/path/file.php')
        );
    }

    public function test_get_metadata(): void
    {
        $this->assertNull($this->config->getMetadata());
    }

    public function test_configure_metadata(): void
    {
        $this->setConfig(['metadata' => 123]);

        $this->assertSame(123, $this->config->getMetadata());
    }

    public function test_get_output_path(): void
    {
        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'default.phar',
            $this->config->getOutputPath()
        );
    }

    public function test_configure_output_path(): void
    {
        $this->setConfig(['output' => 'test.phar']);

        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test.phar',
            $this->config->getOutputPath()
        );
    }

    public function test_configure_output_path_with_placeholder(): void
    {
        touch('test');
        exec('git init');
        exec('git config user.name "Test User"');
        exec('git config user.email test@test.test');
        exec('git config commit.gpgsign false');
        exec('git add test');
        exec('git commit -m "Adding test file."');
        exec('git tag 1.0.0');

        $this->setConfig(['output' => 'test-@git-version@.phar']);

        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test-1.0.0.phar',
            $this->config->getOutputPath()
        );

        // Some process does not release the git files
        if ($this->isWindows()) {
            exec('rd /S /Q .git');
        }
    }

    public function testGetPrivateKeyPassphrase(): void
    {
        $this->assertNull($this->config->getPrivateKeyPassphrase());
    }

    public function testGetPrivateKeyPassphraseSet(): void
    {
        $this->setConfig(['key-pass' => 'test']);

        $this->assertSame('test', $this->config->getPrivateKeyPassphrase());
    }

    public function testGetPrivateKeyPassphraseSetBoolean(): void
    {
        $this->setConfig(['key-pass' => true]);

        $this->assertNull($this->config->getPrivateKeyPassphrase());
    }

    public function testGetPrivateKeyPath(): void
    {
        $this->assertNull($this->config->getPrivateKeyPath());
    }

    public function testGetPrivateKeyPathSet(): void
    {
        $this->setConfig(['key' => 'test.pem']);

        $this->assertSame('test.pem', $this->config->getPrivateKeyPath());
    }

    public function testGetProcessedReplacements(): void
    {
        $this->assertSame([], $this->config->getProcessedReplacements());
    }

    public function testGetProcessedReplacementsSet(): void
    {
        touch('test');
        exec('git init');
        exec('git config user.name "Test User"');
        exec('git config user.email test@test.test');
        exec('git config commit.gpgsign false');
        exec('git add test');
        exec('git commit -m "Adding test file."');
        exec('git tag 1.0.0');

        $this->setConfig(
            [
                'git-commit' => 'git_commit',
                'git-commit-short' => 'git_commit_short',
                'git-tag' => 'git_tag',
                'git-version' => 'git_version',
                'replacements' => ['rand' => $rand = random_int(0, getrandmax())],
                'datetime' => 'date_time',
                'datetime_format' => 'Y:m:d',
            ]
        );

        $values = $this->config->getProcessedReplacements();

        $this->assertRegExp('/^[a-f0-9]{40}$/', $values['@git_commit@']);
        $this->assertRegExp('/^[a-f0-9]{7}$/', $values['@git_commit_short@']);
        $this->assertSame('1.0.0', $values['@git_tag@']);
        $this->assertSame('1.0.0', $values['@git_version@']);
        $this->assertSame($rand, $values['@rand@']);
        $this->assertRegExp(
            '/^[0-9]{4}:[0-9]{2}:[0-9]{2}$/',
            $values['@date_time@']
        );

        // some process does not release the git files
        if ($this->isWindows()) {
            exec('rd /S /Q .git');
        }
    }

    public function test_the_config_has_no_default_shebang(): void
    {
        $this->assertNull($this->config->getShebang());
    }

    public function test_the_shebang_can_be_configured(): void
    {
        $this->setConfig(['shebang' => $expected = '#!/bin/php']);

        $actual = $this->config->getShebang();

        $this->assertSame($expected, $actual);
    }

    public function test_cannot_register_an_invalid_shebang(): void
    {
        try {
            $this->setConfig(['shebang' => '/bin/php']);

            $this->fail('Expected exception ot be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The shebang line must start with "#!". Got "/bin/php" instead',
                $exception->getMessage()
            );
        }
    }

    public function test_cannot_register_an_empty_shebang(): void
    {
        try {
            $this->setConfig(['shebang' => '']);

            $this->fail('Expected exception ot be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The shebang should not be empty.',
                $exception->getMessage()
            );
        }

        try {
            $this->setConfig(['shebang' => ' ']);

            $this->fail('Expected exception ot be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The shebang should not be empty.',
                $exception->getMessage()
            );
        }
    }

    public function test_the_shebang_value_is_normalized(): void
    {
        $this->setConfig(['shebang' => ' #!/bin/php ']);

        $expected = '#!/bin/php';

        $actual = $this->config->getShebang();

        $this->assertSame($expected, $actual);
    }

    public function testGetSigningAlgorithm(): void
    {
        $this->assertSame(Phar::SHA1, $this->config->getSigningAlgorithm());
    }

    public function testGetSigningAlgorithmSet(): void
    {
        $this->setConfig(['algorithm' => Phar::MD5]);

        $this->assertSame(Phar::MD5, $this->config->getSigningAlgorithm());
    }

    public function testGetSigningAlgorithmSetString(): void
    {
        $this->setConfig(['algorithm' => 'MD5']);

        $this->assertSame(Phar::MD5, $this->config->getSigningAlgorithm());
    }

    public function testGetSigningAlgorithmInvalidString(): void
    {
        try {
            $this->setConfig(['algorithm' => 'INVALID']);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The signing algorithm "INVALID" is not supported.',
                $exception->getMessage()
            );
        }
    }

    public function testGetStubBanner(): void
    {
        $this->assertNull($this->config->getStubBanner());
    }

    public function testGetStubBannerSet(): void
    {
        $comment = <<<'COMMENT'
This is a

multiline

comment.
COMMENT;

        $this->setConfig(['banner' => $comment]);

        $this->assertSame($comment, $this->config->getStubBanner());
    }

    public function testGetStubBannerFromFile(): void
    {
        $this->assertNull($this->config->getStubBannerFromFile());
    }

    public function testGetStubBannerFromFileSet(): void
    {
        $comment = <<<'COMMENT'
This is a

multiline

comment.
COMMENT;

        file_put_contents('banner', $comment);

        $this->setConfig(['banner-file' => 'banner']);

        $this->assertSame($comment, $this->config->getStubBannerFromFile());
    }

    public function testGetStubBannerFromFileReadError(): void
    {
        try {
            $this->setConfig(['banner-file' => '/does/not/exist']);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'File "/does/not/exist" was expected to exist.',
                $exception->getMessage()
            );
        }
    }

    public function testGetStubBannerPath(): void
    {
        $this->assertNull($this->config->getStubBannerPath());
    }

    public function testGetStubBannerPathSet(): void
    {
        touch('path-to-file');

        $this->setConfig(['banner-file' => 'path-to-file']);

        $this->assertSame(
            'path-to-file',
            $this->config->getStubBannerPath()
        );
    }

    public function testGetStubPath(): void
    {
        $this->assertNull($this->config->getStubPath());
    }

    public function testGetStubPathSet(): void
    {
        $this->setConfig(['stub' => 'test.php']);

        $this->assertSame('test.php', $this->config->getStubPath());
    }

    public function testGetStubPathSetBoolean(): void
    {
        $this->setConfig(['stub' => true]);

        $this->assertNull($this->config->getStubPath());
    }

    public function testIsInterceptFileFuncs(): void
    {
        $this->assertFalse($this->config->isInterceptFileFuncs());
    }

    public function testIsInterceptFileFuncsSet(): void
    {
        $this->setConfig(['intercept' => true]);

        $this->assertTrue($this->config->isInterceptFileFuncs());
    }

    public function testIsPrivateKeyPrompt(): void
    {
        $this->assertFalse($this->config->isPrivateKeyPrompt());
    }

    public function testIsPrivateKeyPromptSet(): void
    {
        $this->setConfig(['key-pass' => true]);

        $this->assertTrue($this->config->isPrivateKeyPrompt());
    }

    public function testIsPrivateKeyPromptSetString(): void
    {
        $this->setConfig(['key-pass' => 'test']);

        $this->assertFalse($this->config->isPrivateKeyPrompt());
    }

    public function testIsStubGenerated(): void
    {
        $this->assertFalse($this->config->isStubGenerated());
    }

    public function testIsStubGeneratedSet(): void
    {
        $this->setConfig(['stub' => true]);

        $this->assertTrue($this->config->isStubGenerated());
    }

    public function testIsStubGeneratedSetString(): void
    {
        $this->setConfig(['stub' => 'test.php']);

        $this->assertFalse($this->config->isStubGenerated());
    }

    public function testLoadBootstrap(): void
    {
        file_put_contents(
            'test.php',
            <<<'CODE'
<?php define('TEST_BOOTSTRAP_FILE_LOADED', true);
CODE
        );

        $this->setConfig(['bootstrap' => 'test.php']);

        $this->config->loadBootstrap();

        $this->assertTrue(defined('TEST_BOOTSTRAP_FILE_LOADED'));
    }

    public function testLoadBootstrapNotExist(): void
    {
        try {
            $this->setConfig(['bootstrap' => 'test.php']);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The bootstrap path "'.$this->tmp.DIRECTORY_SEPARATOR.'test.php" is not a file or does not exist.',
                $exception->getMessage()
            );
        }
    }

    public function provideInvalidCompressionAlgorithms(): Generator
    {
        yield 'Invalid string key' => [
            'INVALID',
            'Invalid compression algorithm "INVALID", use one of "GZ", "BZ2", "NONE" instead.',
        ];

        yield 'Invalid constant value' => [
            10,
            'Invalid compression algorithm "10", use one of "GZ", "BZ2", "NONE" instead.',
        ];

        yield 'Invalid type 1' => [
            [],
            'Expected compression to be an algorithm name, found <ARRAY> instead.',
        ];

        yield 'Invalid type 2' => [
            new stdClass(),
            'Expected compression to be an algorithm name, found stdClass instead.',
        ];
    }

    public function provideJsonValidNonStringValues(): Generator
    {
        foreach ($this->provideJsonPrimitives() as $key => $value) {
            if ('string' === $key) {
                continue;
            }

            yield $key => [$value];
        }
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

    private function provideJsonPrimitives(): Generator
    {
        yield 'null' => null;
        yield 'bool' => true;
        yield 'number' => 30;
        yield 'string' => 'foo';
        yield 'object' => ['foo' => 'bar'];
        yield 'array' => ['foo', 'bar'];
    }

    private function setConfig(array $config): void
    {
        file_put_contents($this->file, json_encode($config, JSON_PRETTY_PRINT));

        $configHelper = new ConfigurationHelper();

        $this->config = $configHelper->loadFile($this->file);
    }

    private function isWindows(): bool
    {
        return false === strpos(strtolower(PHP_OS), 'darwin') && false !== strpos(strtolower(PHP_OS), 'win');
    }

    /**
     * @param SplFileInfo[] $files
     *
     * @return string[] File real paths relative to the current temporary directory
     */
    private function normalizeConfigPaths(array $files): array
    {
        $root = $this->tmp;

        return array_values(
            array_map(
                function (SplFileInfo $fileInfo) use ($root): string {
                    $path = $fileInfo->getRealPath();

                    return str_replace($root.DIRECTORY_SEPARATOR, '', $path);
                },
                $files
            )
        );
    }
}
