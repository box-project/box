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
use KevinGH\Box\Json\JsonValidationException;
use Phar;
use Seld\JsonLint\ParsingException;
use stdClass;
use const DIRECTORY_SEPARATOR;
use function file_put_contents;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\remove;
use function KevinGH\Box\FileSystem\rename;

/**
 * @covers \KevinGH\Box\Configuration
 */
class ConfigurationTest extends ConfigurationTestCase
{
    public function test_it_can_be_created_with_a_file(): void
    {
        $config = Configuration::create('box.json', new stdClass());

        $this->assertSame('box.json', $config->getFile());
    }

    public function test_it_can_be_created_without_a_file(): void
    {
        $config = Configuration::create(null, new stdClass());

        $this->assertNull($config->getFile());
    }

    public function test_a_default_alias_is_generted_if_no_alias_is_registered(): void
    {
        $this->assertRegExp('/^box-auto-generated-alias-[\da-zA-Z]{13}\.phar$/', $this->config->getAlias());
        $this->assertRegExp('/^box-auto-generated-alias-[\da-zA-Z]{13}\.phar$/', $this->getNoFileConfig()->getAlias());
    }

    public function test_the_alias_can_be_configured(): void
    {
        $this->setConfig([
            'alias' => 'test.phar',
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertSame('test.phar', $this->config->getAlias());
    }

    public function test_the_alias_value_is_normalized(): void
    {
        $this->setConfig([
            'alias' => '  test.phar  ',
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertSame('test.phar', $this->config->getAlias());
    }

    public function test_the_alias_cannot_be_empty(): void
    {
        try {
            $this->setConfig([
                'alias' => '',
                'files' => [self::DEFAULT_FILE],
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'A PHAR alias cannot be empty when provided.',
                $exception->getMessage()
            );
        }
    }

    public function test_the_alias_must_be_a_string(): void
    {
        try {
            $this->setConfig([
                'alias' => true,
                'files' => [self::DEFAULT_FILE],
            ]);

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

    public function test_the_default_base_path_used_is_the_configuration_file_location(): void
    {
        dump_file('sub-dir/box.json', '{}');
        dump_file('sub-dir/index.php');

        $this->file = $this->tmp.'/sub-dir/box.json';

        $this->reloadConfig();

        $this->assertSame($this->tmp.'/sub-dir', $this->config->getBasePath());
    }

    public function test_if_there_is_no_file_the_default_base_path_used_is_the_current_working_directory(): void
    {
        $this->assertSame($this->tmp, $this->getNoFileConfig()->getBasePath());
    }

    public function test_the_base_path_can_be_configured(): void
    {
        mkdir($basePath = $this->tmp.DIRECTORY_SEPARATOR.'test');
        rename(self::DEFAULT_FILE, $basePath.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        $this->setConfig([
                'base-path' => $basePath,
        ]);

        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test',
            $this->config->getBasePath()
        );
    }

    public function test_a_non_existent_directory_cannot_be_used_as_a_base_path(): void
    {
        try {
            $this->setConfig([
                'base-path' => $this->tmp.DIRECTORY_SEPARATOR.'test',
                'files' => [self::DEFAULT_FILE],
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The base path "'.$this->tmp.DIRECTORY_SEPARATOR.'test" is not a directory or does not exist.',
                $exception->getMessage()
            );
        }
    }

    public function test_a_file_path_cannot_be_used_as_a_base_path(): void
    {
        touch('foo');

        try {
            $this->setConfig([
                'base-path' => $this->tmp.DIRECTORY_SEPARATOR.'foo',
                'files' => [self::DEFAULT_FILE],
            ]);

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
        rename(self::DEFAULT_FILE, 'dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        $this->setConfig([
            'base-path' => 'dir',
        ]);

        $expected = $this->tmp.DIRECTORY_SEPARATOR.'dir';

        $this->assertSame($expected, $this->config->getBasePath());
    }

    public function test_the_base_path_value_is_normalized(): void
    {
        mkdir('dir');
        rename(self::DEFAULT_FILE, 'dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        $this->setConfig([
            'base-path' => ' dir ',
        ]);

        $expected = $this->tmp.DIRECTORY_SEPARATOR.'dir';

        $this->assertSame($expected, $this->config->getBasePath());
    }

    /**
     * @dataProvider provideJsonFiles
     */
    public function test_it_attempts_to_get_and_decode_the_json_and_lock_files(
        callable $setup,
        ?string $expectedJson,
        ?array $expectedJsonContents,
        ?string $expectedLock,
        ?array $expectedLockContents
    ): void {
        $setup();

        if (null !== $expectedJson) {
            $expectedJson = $this->tmp.DIRECTORY_SEPARATOR.$expectedJson;
        }

        if (null !== $expectedLock) {
            $expectedLock = $this->tmp.DIRECTORY_SEPARATOR.$expectedLock;
        }

        $this->reloadConfig();

        $this->assertSame($expectedJson, $this->config->getComposerJson());
        $this->assertSame($expectedJsonContents, $this->config->getComposerJsonDecodedContents());

        $this->assertSame($expectedLock, $this->config->getComposerLock());
        $this->assertSame($expectedLockContents, $this->config->getComposerLockDecodedContents());
    }

    public function test_it_throws_an_error_when_a_composer_file_is_found_but_invalid(): void
    {
        file_put_contents('composer.json', '');

        try {
            $this->reloadConfig();
        } catch (InvalidArgumentException $exception) {
            $composerJson = $this->tmp.'/composer.json';

            $this->assertSame(
                <<<EOF
Expected the file "$composerJson" to be a valid composer.json file but an error has been found: Parse error on line 1:

^
Expected one of: 'STRING', 'NUMBER', 'NULL', 'TRUE', 'FALSE', '{', '['
EOF
                ,
                $exception->getMessage()
            );
            $this->assertSame(0, $exception->getCode());
            $this->assertInstanceOf(ParsingException::class, $exception->getPrevious());
        }
    }

    public function test_it_throws_an_error_when_a_composer_lock_is_found_but_invalid(): void
    {
        file_put_contents('composer.lock', '');

        try {
            $this->reloadConfig();

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $composerLock = $this->tmp.'/composer.lock';

            $this->assertSame(
                <<<EOF
Expected the file "$composerLock" to be a valid composer.json file but an error has been found: Parse error on line 1:

^
Expected one of: 'STRING', 'NUMBER', 'NULL', 'TRUE', 'FALSE', '{', '['
EOF
                ,
                $exception->getMessage()
            );
            $this->assertSame(0, $exception->getCode());
            $this->assertInstanceOf(ParsingException::class, $exception->getPrevious());
        }
    }

    public function test_the_autoloader_is_dumped_by_default_if_a_composer_json_file_is_found(): void
    {
        $this->assertFalse($this->config->dumpAutoload());
        $this->assertFalse($this->getNoFileConfig()->dumpAutoload());

        $this->setConfig(['dump-autoload' => null]);

        $this->assertFalse($this->config->dumpAutoload());

        file_put_contents('composer.json', '{}');

        $this->setConfig([]);

        $this->assertTrue($this->config->dumpAutoload());
        $this->assertTrue($this->getNoFileConfig()->dumpAutoload());

        $this->setConfig(['dump-autoload' => null]);

        $this->assertTrue($this->config->dumpAutoload());
    }

    public function test_the_autoloader_is_can_be_configured(): void
    {
        file_put_contents('composer.json', '{}');

        $this->setConfig([
            'dump-autoload' => false,
        ]);

        $this->assertFalse($this->config->dumpAutoload());
        $this->assertTrue($this->getNoFileConfig()->dumpAutoload());

        $this->setConfig([
            'dump-autoload' => true,
        ]);

        $this->assertTrue($this->config->dumpAutoload());
        $this->assertTrue($this->getNoFileConfig()->dumpAutoload());
    }

    public function test_the_autoloader_cannot_be_dumped_if_no_composer_json_file_is_found(): void
    {
        $this->setConfig([
            'dump-autoload' => true,
        ]);

        $this->assertFalse($this->config->dumpAutoload());
    }

    public function test_no_compactors_is_configured_by_default(): void
    {
        $this->assertSame([], $this->config->getCompactors());
        $this->assertSame([], $this->getNoFileConfig()->getCompactors());
    }

    public function test_configure_the_compactors(): void
    {
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'compactors' => [
                Php::class,
                DummyCompactor::class,
            ],
        ]);

        $compactors = $this->config->getCompactors();

        $this->assertInstanceOf(Php::class, $compactors[0]);
        $this->assertInstanceOf(DummyCompactor::class, $compactors[1]);
    }

    public function test_it_cannot_get_the_compactors_with_an_invalid_class(): void
    {
        try {
            $this->setConfig([
                'files' => [self::DEFAULT_FILE],
                'compactors' => ['NoSuchClass'],
            ]);

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
            $this->setConfig([
                'compactors' => [InvalidCompactor::class],
                'files' => [self::DEFAULT_FILE],
            ]);

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
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'annotations' => (object) [
                'ignore' => [
                    'author',
                ],
            ],
            'compactors' => [
                Php::class,
            ],
        ]);

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

    public function test_no_compression_algorithm_is_configured_by_default(): void
    {
        $this->assertNull($this->config->getCompressionAlgorithm());
        $this->assertNull($this->getNoFileConfig()->getCompressionAlgorithm());
    }

    public function test_the_compression_algorithm_with_a_string(): void
    {
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'compression' => 'BZ2',
        ]);

        $this->assertSame(Phar::BZ2, $this->config->getCompressionAlgorithm());
    }

    /**
     * @dataProvider provideInvalidCompressionAlgorithms
     *
     * @param mixed $compression
     */
    public function test_the_compression_algorithm_cannot_be_an_invalid_algorithm($compression, string $errorMessage): void
    {
        try {
            $this->setConfig([
                'files' => [self::DEFAULT_FILE],
                'compression' => $compression,
            ]);

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

    public function test_no_file_mode_is_configured_by_default(): void
    {
        $this->assertNull($this->config->getFileMode());
        $this->assertNull($this->getNoFileConfig()->getFileMode());
    }

    public function test_configure_file_mode(): void
    {
        // Octal value provided
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'chmod' => '0755',
        ]);

        $this->assertSame(0755, $this->config->getFileMode());

        // Decimal value provided
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'chmod' => '755',
        ]);

        $this->assertSame(0755, $this->config->getFileMode());
    }

    public function test_a_main_script_path_is_configured_by_default(): void
    {
        dump_file('composer.json', '{"bin": []}');

        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'index.php', $this->config->getMainScriptPath());
        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'index.php', $this->getNoFileConfig()->getMainScriptPath());
    }

    public function test_a_main_script_path_is_inferred_by_the_composer_json_by_default(): void
    {
        dump_file('bin/foo');

        dump_file(
            'composer.json',
            <<<'JSON'
{
    "bin": "bin/foo"
}
JSON
        );

        $this->reloadConfig();

        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'bin/foo', $this->config->getMainScriptPath());
        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'bin/foo', $this->getNoFileConfig()->getMainScriptPath());
    }

    public function test_the_first_composer_bin_is_used_as_the_main_script_by_default(): void
    {
        dump_file('bin/foo');
        dump_file('bin/bar');

        dump_file(
            'composer.json',
            <<<'JSON'
{
    "bin": [
        "bin/foo",
        "bin/bar"
    ]
}
JSON
        );

        $this->reloadConfig();

        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'bin/foo', $this->config->getMainScriptPath());
        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'bin/foo', $this->getNoFileConfig()->getMainScriptPath());
    }

    public function test_main_script_can_be_configured(): void
    {
        touch('test.php');

        dump_file('bin/foo');
        dump_file('bin/bar');

        dump_file(
            'composer.json',
            <<<'JSON'
{
    "bin": [
        "bin/foo",
        "bin/bar"
    ]
}
JSON
        );

        $this->setConfig(['main' => 'test.php']);

        $this->assertSame($this->tmp.'/test.php', $this->config->getMainScriptPath());
    }

    public function test_main_script_path_is_normalized(): void
    {
        touch('test.php');

        $this->setConfig(['main' => ' test.php ']);

        $this->assertSame($this->tmp.'/test.php', $this->config->getMainScriptPath());
    }

    public function test_get_main_script_content(): void
    {
        dump_file(self::DEFAULT_FILE, $expected = 'Default main script content');

        $this->reloadConfig();

        $this->assertSame($expected, $this->config->getMainScriptContents());
    }

    public function test_configure_main_script_content(): void
    {
        file_put_contents('test.php', 'script content');

        $this->setConfig(['main' => 'test.php']);

        $this->assertSame('script content', $this->config->getMainScriptContents());
    }

    public function test_main_script_content_ignores_shebang_line(): void
    {
        file_put_contents('test.php', "#!/usr/bin/env php\ntest");

        $this->setConfig(['main' => 'test.php']);

        $this->assertSame('test', $this->config->getMainScriptContents());
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
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'map' => [
                ['a' => 'b'],
                ['_empty_' => 'c'],
            ],
        ]);

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
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'map' => [
                ['first/test/path' => 'a'],
                ['' => 'b/'],
            ],
        ]);

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

    public function test_no_metadata_is_configured_by_default(): void
    {
        $this->assertNull($this->config->getMetadata());
    }

    public function test_can_configure_metadata(): void
    {
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'metadata' => 123,
        ]);

        $this->assertSame(123, $this->config->getMetadata());
    }

    public function test_get_default_output_path(): void
    {
        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'index.phar',
            $this->config->getOutputPath()
        );
        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'index.phar',
            $this->config->getTmpOutputPath()
        );
    }

    public function test_the_output_path_is_configurable(): void
    {
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'output' => 'test.phar',
        ]);

        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test.phar',
            $this->config->getOutputPath()
        );
        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test.phar',
            $this->config->getTmpOutputPath()
        );
    }

    public function test_the_output_path_is_relative_to_the_base_path(): void
    {
        mkdir('sub-dir');
        rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        $this->setConfig([
            'output' => 'test.phar',
            'base-path' => 'sub-dir',
        ]);

        $this->assertSame(
            $this->tmp.'/sub-dir/test.phar',
            $this->config->getOutputPath()
        );
        $this->assertSame(
            $this->tmp.'/sub-dir/test.phar',
            $this->config->getTmpOutputPath()
        );
    }

    public function test_the_output_path_is_not_relative_to_the_base_path_if_is_absolute(): void
    {
        mkdir('sub-dir');
        rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        $this->setConfig([
            'output' => $this->tmp.'/test.phar',
            'base-path' => 'sub-dir',
        ]);

        $this->assertSame(
            $this->tmp.'/test.phar',
            $this->config->getOutputPath()
        );
        $this->assertSame(
            $this->tmp.'/test.phar',
            $this->config->getTmpOutputPath()
        );
    }

    public function test_the_output_path_is_normalized(): void
    {
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'output' => ' test.phar ',
        ]);

        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test.phar',
            $this->config->getOutputPath()
        );
        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test.phar',
            $this->config->getTmpOutputPath()
        );
    }

    public function test_the_output_path_can_omit_the_PHAR_extension(): void
    {
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'output' => 'test',
        ]);

        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test',
            $this->config->getOutputPath()
        );
        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test.phar',
            $this->config->getTmpOutputPath()
        );
    }

    public function test_get_default_output_path_depends_on_the_input(): void
    {
        dump_file('bin/acme');

        $this->setConfig([
            'main' => 'bin/acme',
        ]);

        $this->assertSame(
            $this->tmp.'/bin/acme.phar',
            $this->config->getOutputPath()
        );
    }

    public function testGetPrivateKeyPassphrase(): void
    {
        $this->assertNull($this->config->getPrivateKeyPassphrase());
    }

    public function testGetPrivateKeyPassphraseSet(): void
    {
        $this->setConfig([
            'key-pass' => 'test',
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertSame('test', $this->config->getPrivateKeyPassphrase());
    }

    public function testGetPrivateKeyPassphraseSetBoolean(): void
    {
        $this->setConfig([
            'key-pass' => true,
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertNull($this->config->getPrivateKeyPassphrase());
    }

    public function testGetPrivateKeyPath(): void
    {
        $this->assertNull($this->config->getPrivateKeyPath());
    }

    public function testGetPrivateKeyPathSet(): void
    {
        $this->setConfig([
            'key' => 'test.pem',
            'files' => [self::DEFAULT_FILE],
        ]);

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

        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'git-commit' => 'git_commit',
            'git-commit-short' => 'git_commit_short',
            'git-tag' => 'git_tag',
            'git-version' => 'git_version',
            'replacements' => ['rand' => $rand = random_int(0, getrandmax())],
            'datetime' => 'date_time',
            'datetime_format' => 'Y:m:d',
        ]);

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

    public function test_the_config_has_a_default_shebang(): void
    {
        $this->assertSame('#!/usr/bin/env php', $this->config->getShebang());
    }

    public function test_the_shebang_can_be_configured(): void
    {
        $this->setConfig([
            'shebang' => $expected = '#!/bin/php',
            'files' => [self::DEFAULT_FILE],
        ]);

        $actual = $this->config->getShebang();

        $this->assertSame($expected, $actual);
    }

    public function test_the_shebang_can_be_disabled(): void
    {
        $this->setConfig([
            'shebang' => null,
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertNull($this->config->getShebang());
    }

    public function test_cannot_register_an_invalid_shebang(): void
    {
        try {
            $this->setConfig([
                'shebang' => '/bin/php',
                'files' => [self::DEFAULT_FILE],
            ]);

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
            $this->setConfig([
                'shebang' => '',
                'files' => [self::DEFAULT_FILE],
            ]);

            $this->fail('Expected exception ot be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The shebang should not be empty.',
                $exception->getMessage()
            );
        }

        try {
            $this->setConfig([
                'shebang' => ' ',
                'files' => [self::DEFAULT_FILE],
            ]);

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
        $this->setConfig([
            'shebang' => ' #!/bin/php ',
            'files' => [self::DEFAULT_FILE],
        ]);

        $expected = '#!/bin/php';

        $actual = $this->config->getShebang();

        $this->assertSame($expected, $actual);
    }

    public function testGetSigningAlgorithm(): void
    {
        $this->assertSame(Phar::SHA1, $this->config->getSigningAlgorithm());
    }

    public function testGetSigningAlgorithmSetString(): void
    {
        $this->setConfig([
            'algorithm' => 'MD5',
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertSame(Phar::MD5, $this->config->getSigningAlgorithm());
    }

    public function testGetSigningAlgorithmInvalidString(): void
    {
        try {
            $this->setConfig([
                'algorithm' => 'INVALID',
                'files' => [self::DEFAULT_FILE],
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The signing algorithm "INVALID" is not supported.',
                $exception->getMessage()
            );
        }
    }

    public function test_there_is_a_banner_registered_by_default(): void
    {
        $expected = <<<'BANNER'
Generated by Humbug Box.

@link https://github.com/humbug/box
BANNER;

        $this->assertSame($expected, $this->config->getStubBannerContents());
        $this->assertNull($this->config->getStubBannerPath());
    }

    /**
     * @dataProvider provideCustomBanner
     */
    public function test_a_custom_banner_can_be_registered(string $banner): void
    {
        $this->setConfig([
            'banner' => $banner,
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertSame($banner, $this->config->getStubBannerContents());
        $this->assertNull($this->config->getStubBannerPath());
    }

    public function test_the_banner_can_be_disabled(): void
    {
        $this->setConfig([
            'banner' => null,
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertNull($this->config->getStubBannerContents());
        $this->assertNull($this->config->getStubBannerPath());
    }

    /**
     * @dataProvider provideUnormalizedCustomBanner
     */
    public function test_the_content_of_the_banner_is_normalized(string $banner, string $expected): void
    {
        $this->setConfig([
            'banner' => $banner,
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertSame($expected, $this->config->getStubBannerContents());
        $this->assertNull($this->config->getStubBannerPath());
    }

    public function test_a_custom_multiline_banner_can_be_registered(): void
    {
        $comment = <<<'COMMENT'
This is a

multiline

comment.
COMMENT;

        $this->setConfig([
            'banner' => $comment,
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertSame($comment, $this->config->getStubBannerContents());
        $this->assertNull($this->config->getStubBannerPath());
    }

    public function test_a_custom_banner_from_a_file_can_be_registered(): void
    {
        $comment = <<<'COMMENT'
This is a

multiline

comment.
COMMENT;

        file_put_contents('banner', $comment);

        $this->setConfig([
            'banner-file' => 'banner',
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertSame($comment, $this->config->getStubBannerContents());
        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'banner', $this->config->getStubBannerPath());
    }

    public function test_the_content_of_the_custom_banner_file_is_normalized(): void
    {
        $comment = <<<'COMMENT'
 This is a 
 
 multiline 
 
 comment. 
COMMENT;

        $expected = <<<'COMMENT'
This is a

multiline

comment.
COMMENT;

        file_put_contents('banner', $comment);

        $this->setConfig([
            'banner-file' => 'banner',
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertSame($expected, $this->config->getStubBannerContents());
        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'banner', $this->config->getStubBannerPath());
    }

    public function test_the_custom_banner_file_must_exists_when_provided(): void
    {
        try {
            $this->setConfig([
                'banner-file' => '/does/not/exist',
                'files' => [self::DEFAULT_FILE],
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'File "/does/not/exist" was expected to exist.',
                $exception->getMessage()
            );
        }
    }

    public function test_by_default_there_is_no_stub_and_the_stub_is_generated(): void
    {
        $this->assertNull($this->config->getStubPath());
        $this->assertTrue($this->config->isStubGenerated());
    }

    public function test_a_custom_stub_can_be_provided(): void
    {
        file_put_contents('custom-stub.php', '');

        $this->setConfig([
            'stub' => 'custom-stub.php',
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'custom-stub.php', $this->config->getStubPath());
        $this->assertFalse($this->config->isStubGenerated());
    }

    public function test_the_stub_can_be_generated(): void
    {
        $this->setConfig([
            'stub' => true,
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertNull($this->config->getStubPath());
        $this->assertTrue($this->config->isStubGenerated());
    }

    public function test_the_default_stub_can_be_used(): void
    {
        $this->setConfig([
            'stub' => false,
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertNull($this->config->getStubPath());
        $this->assertFalse($this->config->isStubGenerated());
    }

    public function testIsInterceptFileFuncs(): void
    {
        $this->assertFalse($this->config->isInterceptFileFuncs());
    }

    public function testIsInterceptFileFuncsSet(): void
    {
        $this->setConfig([
            'intercept' => true,
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertTrue($this->config->isInterceptFileFuncs());
    }

    public function testIsPrivateKeyPrompt(): void
    {
        $this->assertFalse($this->config->isPrivateKeyPrompt());
    }

    public function testIsPrivateKeyPromptSet(): void
    {
        $this->setConfig([
            'key-pass' => true,
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertTrue($this->config->isPrivateKeyPrompt());
    }

    public function testIsPrivateKeyPromptSetString(): void
    {
        $this->setConfig([
            'key-pass' => 'test',
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertFalse($this->config->isPrivateKeyPrompt());
    }

    public function test_the_requirement_checker_is_enabled_by_default_if_a_composer_lock_or_json_file_is_found(): void
    {
        $this->assertFalse($this->config->checkRequirements());

        file_put_contents('composer.lock', '{}');

        $this->reloadConfig();

        $this->assertTrue($this->config->checkRequirements());

        file_put_contents('composer.json', '{}');

        $this->reloadConfig();

        $this->assertTrue($this->config->checkRequirements());

        remove('composer.lock');

        $this->reloadConfig();

        $this->assertTrue($this->config->checkRequirements());
    }

    public function test_the_requirement_checker_can_be_disabled(): void
    {
        $this->setConfig([
            'check-requirements' => false,
        ]);

        $this->assertFalse($this->config->checkRequirements());

        file_put_contents('composer.lock', '{}');

        $this->reloadConfig();

        $this->assertFalse($this->config->checkRequirements());
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

    public function provideJsonPrimitives(): Generator
    {
        yield 'null' => null;
        yield 'bool' => true;
        yield 'number' => 30;
        yield 'string' => 'foo';
        yield 'object' => ['foo' => 'bar'];
        yield 'array' => ['foo', 'bar'];
    }

    public function provideCustomBanner(): Generator
    {
        yield ['Simple banner'];

        yield [<<<'COMMENT'
This is a

multiline

banner.
COMMENT
        ];
    }

    public function provideUnormalizedCustomBanner(): Generator
    {
        yield [
            ' Simple banner ',
            'Simple banner',
        ];

        yield [
            <<<'COMMENT'
 This is a 
 
 multiline 
 
 banner. 
COMMENT
            ,
            <<<'COMMENT'
This is a

multiline

banner.
COMMENT
        ];
    }

    public function provideJsonFiles()
    {
        yield [
            function (): void {},
            null,
            null,
            null,
            null,
        ];

        yield [
            function (): void {
                file_put_contents('composer.json', '{}');
            },
            'composer.json',
            [],
            null,
            null,
        ];

        yield [
            function (): void {
                file_put_contents('composer.json', '{"name": "acme/foo"}');
            },
            'composer.json',
            ['name' => 'acme/foo'],
            null,
            null,
        ];

        yield [
            function (): void {
                file_put_contents('composer.lock', '{}');
            },
            null,
            null,
            'composer.lock',
            [],
        ];

        yield [
            function (): void {
                file_put_contents('composer.lock', '{"name": "acme/foo"}');
            },
            null,
            null,
            'composer.lock',
            ['name' => 'acme/foo'],
        ];

        yield [
            function (): void {
                file_put_contents('composer.json', '{"name": "acme/foo"}');
                file_put_contents('composer.lock', '{"name": "acme/bar"}');
            },
            'composer.json',
            ['name' => 'acme/foo'],
            'composer.lock',
            ['name' => 'acme/bar'],
        ];

        yield [
            function (): void {
                mkdir('composer.json');
            },
            null,
            null,
            null,
            null,
        ];

        yield [
            function (): void {
                mkdir('composer.lock');
            },
            null,
            null,
            null,
            null,
        ];

        yield [
            function (): void {
                touch('composer.json');
                chmod('composer.json', 0000);
            },
            null,
            null,
            null,
            null,
        ];

        yield [
            function (): void {
                touch('composer.lock');
                chmod('composer.lock', 0000);
            },
            null,
            null,
            null,
            null,
        ];
    }
}
