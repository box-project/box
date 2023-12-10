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
use KevinGH\Box\Platform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use function natcasesort;
use function symlink;

/**
 * @internal
 */
#[CoversClass(Configuration::class)]
#[Group('config')]
class ConfigurationFileNoConfigTest extends ConfigurationTestCase
{
    public function test_all_the_files_found_in_the_composer_json_are_taken_by_default_with_no_config_file_is_used(): void
    {
        FS::touch('index.php');
        FS::touch('index.phar');

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

        $noFileConfig = $this->getNoFileConfig();

        $actual = $this->normalizePaths($noFileConfig->getFiles());

        self::assertEquals($expected, $actual);
        self::assertCount(0, $noFileConfig->getBinaryFiles());

        $this->reloadConfig();

        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertEquals($expected, $actual);
        self::assertCount(0, $this->config->getBinaryFiles());

        self::assertSame($this->tmp.'/index.php', $noFileConfig->getMainScriptPath());

        self::assertSame($this->tmp.'/index.phar', $noFileConfig->getOutputPath());
        self::assertSame($this->tmp.'/index.phar', $noFileConfig->getTmpOutputPath());
    }

    public function test_find_psr0_files(): void
    {
        FS::mkdir('PSR0_0');
        FS::touch('PSR0_0/file0');
        FS::touch('PSR0_0/file1');

        FS::dumpFile(
            'composer.json',
            <<<'JSON'
                {
                    "autoload": {
                        "psr-0": {
                            "Acme\\": " PSR0_0 "
                        }
                    }
                }
                JSON,
        );

        // Relative to the current working directory for readability
        $expected = [
            'composer.json',
            'PSR0_0/file0',
            'PSR0_0/file1',
        ];

        $noFileConfig = $this->getNoFileConfig();

        $actual = $this->normalizePaths($noFileConfig->getFiles());

        self::assertEquals($expected, $actual);
        self::assertCount(0, $noFileConfig->getBinaryFiles());

        $this->reloadConfig();

        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertEquals($expected, $actual);
        self::assertCount(0, $this->config->getBinaryFiles());
    }

    public function test_psr0_with_empty_directory_in_composer_json(): void
    {
        FS::touch('root_file0');
        FS::touch('root_file1');

        FS::mkdir('Acme');
        FS::touch('Acme/file0');
        FS::touch('Acme/file1');
        FS::touch('Acme/file2');

        FS::dumpFile(
            'composer.json',
            <<<'JSON'
                {
                    "autoload": {
                        "psr-0": {
                            "Acme\\": ""
                        }
                    }
                }
                JSON,
        );

        $expected = [
            'Acme/file0',
            'Acme/file1',
            'Acme/file2',
            'composer.json',
            'root_file0',
            'root_file1',
        ];

        $noFileConfig = $this->getNoFileConfig();

        $actual = $this->normalizePaths($noFileConfig->getFiles());

        self::assertEquals($expected, $actual);
        self::assertCount(0, $noFileConfig->getBinaryFiles());

        $this->reloadConfig();

        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertEquals($expected, $actual);
        self::assertCount(0, $this->config->getBinaryFiles());
    }

    public function test_throws_an_error_if_a_non_existent_file_is_found_via_the_composer_json(): void
    {
        FS::touch('file0');

        FS::dumpFile(
            'composer.json',
            <<<'JSON'
                {
                    "autoload": {
                        "files": ["file0", "file1"]
                    }
                }
                JSON,
        );

        try {
            $this->getNoFileConfig();

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'The file "'.$this->tmp.'/file1" does not exist.',
                $exception->getMessage(),
            );
        }

        FS::dumpFile(
            'composer.json',
            <<<'JSON'
                {
                    "autoload": {
                        "classmap": ["CLASSMAP_DIR"]
                    }
                }
                JSON,
        );

        try {
            $this->getNoFileConfig();

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'File or directory "'.$this->tmp.'/CLASSMAP_DIR" was expected to exist.',
                $exception->getMessage(),
            );
        }
    }

    public function test_throws_an_error_if_a_symlink_is_used(): void
    {
        FS::touch('file0');
        symlink('file0', 'file1');

        FS::dumpFile(
            'composer.json',
            <<<'JSON'
                {
                    "autoload": {
                        "files": ["file0", "file1"]
                    }
                }
                JSON,
        );

        try {
            $this->getNoFileConfig();

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Cannot add the link "'.$this->tmp.'/file1": links are not supported.',
                $exception->getMessage(),
            );
        }

        FS::dumpFile(
            'composer.json',
            <<<'JSON'
                {
                    "autoload": {
                        "classmap": ["CLASSMAP_DIR"]
                    }
                }
                JSON,
        );
        FS::mkdir('original_dir');
        symlink('original_dir', 'CLASSMAP_DIR');

        try {
            $this->getNoFileConfig();

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Cannot add the link "'.$this->tmp.'/CLASSMAP_DIR": links are not supported.',
                $exception->getMessage(),
            );
        }
    }

    public function test_the_blacklist_setting_is_applied_to_all_the_files_found_in_the_current_directory_are_taken_by_default_if_no_file_setting_is_used(): void
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

        FS::mkdir('BLACKLISTED_CLASSMAP_DIR');
        FS::touch('BLACKLISTED_CLASSMAP_DIR/file0');
        FS::touch('BLACKLISTED_CLASSMAP_DIR/file1');

        FS::mkdir('CLASSMAP_DIR');
        FS::touch('CLASSMAP_DIR/file0');
        FS::touch('CLASSMAP_DIR/file1');

        FS::mkdir('CLASSMAP_DEV_DIR');
        FS::touch('CLASSMAP_DEV_DIR/file0');
        FS::touch('CLASSMAP_DEV_DIR/file1');

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
                        "classmap": [
                            "BLACKLISTED_CLASSMAP_DIR",
                            "CLASSMAP_DIR"
                        ]
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

        $this->setConfig([
            'blacklist' => [
                'file1',
                'BLACKLISTED_CLASSMAP_DIR',
            ],
        ]);

        // Relative to the current working directory for readability
        $expected = [
            'CLASSMAP_DIR/file0',
            'composer.json',
            'file0',
            'PSR0_0/file0',
            'PSR0_1/file0',
            'PSR0_2/file0',
            'PSR4_0/file0',
            'PSR4_1/file0',
            'PSR4_2/file0',
        ];

        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertEquals($expected, $actual);
        self::assertCount(0, $this->config->getBinaryFiles());

        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertEquals($expected, $actual);
        self::assertCount(0, $this->config->getBinaryFiles());
    }

    public function test_it_ignores_the_most_common_non_needed_files_when_guess_the_files_from_the_composer_json_file(): void
    {
        // Depending on the test machine: the following command might be needed:
        // docker run -i --rm -w /opt/box -v "$PWD":/opt/box php:8.1-cli vendor/bin/phpunit tests/Configuration/ConfigurationFileNoConfigTest.php --filter test_it_ignores_the_most_common_non_needed_files_when_guess_the_files_from_the_composer_json_file

        if (Platform::isOSX()) {
            self::markTestSkipped('Cannot run this test on OSX since it is case insensitive.');
        }

        FS::touch('main.php~');
        FS::touch('main.php.back');
        FS::touch('main.php.swp');

        FS::touch('phpunit.xml.dist');
        FS::touch('phpunit.xml');
        FS::touch('phpunit_infection.xml.dist');

        FS::touch('LICENSE');
        FS::touch('LICENSE.md');
        FS::touch('license');
        FS::touch('LICENSE_GECKO');

        FS::touch('License.php');
        FS::touch('LicenseCommand.php');

        FS::touch('README');
        FS::touch('README.md');
        FS::touch('README_ru.md');
        FS::touch('README.rst');
        FS::touch('readme');

        FS::touch('Readme.php');
        FS::touch('ReadmeCommand.php');

        FS::touch('UPGRADE');
        FS::touch('UPGRADE.md');
        FS::touch('upgrade');

        FS::touch('Upgrade.php');
        FS::touch('UpgradeCommand.php');

        FS::touch('CHANGELOG');
        FS::touch('ChangeLog-7.1.md');
        FS::touch('CHANGELOG.md');
        FS::touch('changelog');

        FS::touch('Changelog.php');
        FS::touch('ChangelogCommand.php');

        FS::touch('CONTRIBUTING');
        FS::touch('CONTRIBUTING.md');
        FS::touch('contributing');

        FS::touch('Contributing.php');
        FS::touch('ContributingCommand.php');

        FS::touch('TODO');
        FS::touch('TODO.md');
        FS::touch('todo');

        FS::touch('Todo.php');
        FS::touch('TodoCommand.php');

        FS::touch('CONDUCT');
        FS::touch('CONDUCT.md');
        FS::touch('conduct');
        FS::touch('CODE_OF_CONDUCT.md');

        FS::touch('Conduct.php');
        FS::touch('ConductCommand.php');

        FS::touch('AUTHORS');
        FS::touch('AUTHORS.md');
        FS::touch('authors');

        FS::touch('Author.php');
        FS::touch('AuthorCommand.php');

        FS::touch('Test.php');
        FS::touch('MainTest.php');
        FS::touch('SkippedTest.php');

        FS::touch('Makefile');

        FS::mkdir('doc');
        FS::touch('doc/file0');

        FS::mkdir('docs');
        FS::touch('docs/file0');

        FS::mkdir('src');
        FS::touch('src/.fileB0');
        FS::touch('src/foo.php');
        FS::touch('src/doc.md');
        FS::touch('src/doc.rst');
        FS::touch('src/composer.json');

        FS::mkdir('test');
        FS::touch('test/file0');
        FS::touch('test/Test.php');
        FS::touch('test/MainTest.php');
        FS::touch('test/SkippedTest.php');

        FS::mkdir('tests');
        FS::touch('tests/file0');
        FS::touch('tests/Test.php');
        FS::touch('tests/MainTest.php');
        FS::touch('tests/SkippedTest.php');

        FS::mkdir('src/Test');
        FS::touch('src/Test/file0');
        FS::touch('src/Test/Test.php');
        FS::touch('src/Test/MainTest.php');
        FS::touch('src/Test/SkippedTest.php');

        FS::mkdir('src/Tests');
        FS::touch('src/Tests/file0');
        FS::touch('src/Tests/Test.php');
        FS::touch('src/Tests/MainTest.php');
        FS::touch('src/Tests/SkippedTest.php');

        FS::mkdir('travis');
        FS::touch('travis/install-ev.sh');

        FS::mkdir('.travis');
        FS::touch('.travis/install-ev.sh');

        FS::touch('.travis.yml');
        FS::touch('appveyor.yml');

        FS::touch('phpdoc.dist.xml');
        FS::touch('phpdoc.xml');

        FS::touch('psalm.xml');

        FS::touch('Vagrantfile');

        FS::touch('phpstan.neon.dist');
        FS::touch('phpstan.neon');
        FS::touch('phpstan-test.neon');

        FS::touch('infection.json.dist');
        FS::touch('infection.json');

        FS::touch('humbug.json.dist');
        FS::touch('humbug.json');

        FS::touch('easy-coding-standard.neon');
        FS::touch('easy-coding-standard.neon.dist');

        FS::touch('phpbench.json.dist');
        FS::touch('phpbench.json');

        FS::touch('phpcs.xml.dist');
        FS::touch('phpcs.xml');

        FS::touch('.php_cs.dist');
        FS::touch('.php_cs');
        FS::touch('.php_cs.cache');

        FS::touch('.php-cs-fixer.dist.php');
        FS::touch('.php-cs-fixer.php');
        FS::touch('.php-cs-fixer.cache');

        FS::touch('scoper.inc.php.dist');
        FS::touch('scoper.inc.php');

        FS::mkdir('example');
        FS::touch('example/file0');

        FS::mkdir('examples');
        FS::touch('examples/file0');

        FS::mkdir('build');
        FS::touch('build/file0');

        FS::mkdir('dist');
        FS::touch('dist/file0');

        FS::mkdir('specs');
        FS::touch('specs/file0');

        FS::mkdir('spec');
        FS::touch('spec/MainSpec.php');

        FS::mkdir('features');
        FS::touch('features/acme.feature');

        FS::touch('build.xml.dist');
        FS::touch('build.xml');

        FS::touch('.editorconfig');
        FS::touch('.gitattributes');
        FS::touch('.gitignore');

        FS::touch('behat.yml.dist');
        FS::touch('behat.yml');

        FS::touch('box.json.dist');
        FS::touch('box.json');
        FS::touch('box_dev.json');

        FS::touch('Dockerfile');

        FS::touch('codecov.yml.dist');
        FS::touch('codecov.yml');

        FS::touch('.styleci.yml.dist');
        FS::touch('.styleci.yml');

        FS::touch('.scrutiziner.yml.dist');
        FS::touch('.scrutiziner.yml');

        FS::dumpFile(
            'composer.json',
            <<<'JSON'
                {
                    "autoload": {
                        "classmap": ["./"]
                    }
                }
                JSON,
        );

        // Relative to the current working directory for readability
        $expected = [
            'Author.php',
            'AuthorCommand.php',
            'Changelog.php',
            'ChangelogCommand.php',
            'composer.json',
            'Conduct.php',
            'ConductCommand.php',
            'Contributing.php',
            'ContributingCommand.php',
            'license',
            'LICENSE',
            'License.php',
            'LicenseCommand.php',
            'LICENSE_GECKO',
            'Readme.php',
            'ReadmeCommand.php',
            'src/foo.php',
            'src/Test/file0',
            'src/Test/Test.php',
            'src/Test/MainTest.php',
            'src/Test/SkippedTest.php',
            'test/file0',
            'test/MainTest.php',
            'test/SkippedTest.php',
            'test/Test.php',
            'Test.php',
            'MainTest.php',
            'SkippedTest.php',
            'Todo.php',
            'TodoCommand.php',
            'Upgrade.php',
            'UpgradeCommand.php',
        ];
        natsort($expected);
        natcasesort($expected);
        $expected = array_values($expected);

        $noFileConfig = $this->getNoFileConfig();

        $actual = $this->normalizePaths($noFileConfig->getFiles());

        self::assertSame($expected, $actual);
        self::assertCount(0, $noFileConfig->getBinaryFiles());

        $this->reloadConfig();

        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertEqualsCanonicalizing($expected, $actual);
        self::assertCount(0, $this->config->getBinaryFiles());
    }

    public function test_the_existing_phars_are_ignored_when_all_the_files_are_collected(): void
    {
        FS::touch('index.phar');

        // Relative to the current working directory for readability
        $expected = [];

        $this->reloadConfig();

        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertEquals($expected, $actual);
        self::assertCount(0, $this->config->getBinaryFiles());

        FS::remove('index.phar');
        FS::touch('default');

        // Relative to the current working directory for readability
        $expected = [];

        $this->setConfig([
            'output' => 'default',
        ]);

        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertEquals($expected, $actual);
        self::assertCount(0, $this->config->getBinaryFiles());
    }

    public function test_the_box_debug_directory_is_always_excluded(): void
    {
        FS::touch('file0');
        FS::touch('file1');

        FS::mkdir('.box_dump');
        FS::touch('.box_dump/file0');
        FS::touch('.box_dump/file1');

        FS::mkdir('A');
        FS::touch('A/fileA0');
        FS::touch('A/fileA1');

        FS::dumpFile(
            'composer.json',
            <<<'JSON'
                {
                    "autoload": {
                        "classmap": ["./"]
                    }
                }
                JSON,
        );

        // Relative to the current working directory for readability
        $expected = [
            'A/fileA0',
            'A/fileA1',
            'composer.json',
            'file0',
            'file1',
        ];

        $noFileConfig = $this->getNoFileConfig();

        $actual = $this->normalizePaths($noFileConfig->getFiles());

        self::assertEquals($expected, $actual);
        self::assertCount(0, $noFileConfig->getBinaryFiles());
    }

    public function test_it_includes_the_vendor_files_when_found(): void
    {
        FS::dumpFile('vendor/composer/installed.json', '{}');
        FS::dumpFile('composer.json', '{}');
        FS::dumpFile('composer.lock', '{}');

        // Relative to the current working directory for readability
        $expected = [
            'box.json',
            'composer.json',
            'composer.lock',
            'vendor/composer/installed.json',
        ];

        $noFileConfig = $this->getNoFileConfig();

        $actual = $this->normalizePaths($noFileConfig->getFiles());

        self::assertEquals($expected, $actual);
        self::assertCount(0, $noFileConfig->getBinaryFiles());

        $this->reloadConfig();

        $actual = $this->normalizePaths($this->config->getFiles());

        self::assertEquals($expected, $actual);
        self::assertCount(0, $this->config->getBinaryFiles());
    }
}
