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

use function abs;
use function array_fill_keys;
use function array_keys;
use function count;
use function date_default_timezone_set;
use DateTimeImmutable;
use const DIRECTORY_SEPARATOR;
use function exec;
use function getcwd;
use InvalidArgumentException;
use function json_decode;
use const JSON_THROW_ON_ERROR;
use KevinGH\Box\Compactor\DummyCompactor;
use KevinGH\Box\Compactor\InvalidCompactor;
use KevinGH\Box\Compactor\Php;
use KevinGH\Box\Compactor\PhpScoper;
use function KevinGH\Box\FileSystem\chmod;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\file_contents;
use function KevinGH\Box\FileSystem\mkdir;
use function KevinGH\Box\FileSystem\remove;
use function KevinGH\Box\FileSystem\rename;
use function KevinGH\Box\FileSystem\touch;
use function KevinGH\Box\get_box_version;
use KevinGH\Box\Json\JsonValidationException;
use KevinGH\Box\MapFile;
use KevinGH\Box\VarDumperNormalizer;
use function mt_getrandmax;
use Phar;
use const PHP_EOL;
use function random_int;
use RuntimeException;
use Seld\JsonLint\ParsingException;
use function sprintf;
use stdClass;
use function strtolower;

/**
 * @covers \KevinGH\Box\Configuration\Configuration
 * @covers \KevinGH\Box\MapFile
 *
 * @group config
 */
class ConfigurationTest extends ConfigurationTestCase
{
    private static string $version;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$version = get_box_version();
    }

    public function test_it_can_be_created_with_a_file(): void
    {
        $config = Configuration::create('box.json', new stdClass());

        $this->assertSame('box.json', $config->getConfigurationFile());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_it_can_be_created_without_a_file(): void
    {
        $config = Configuration::create(null, new stdClass());

        $this->assertNull($config->getConfigurationFile());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_default_alias_is_generated_if_no_alias_is_registered(): void
    {
        $this->assertMatchesRegularExpression('/^box-auto-generated-alias-[\da-zA-Z]{12}\.phar$/', $this->config->getAlias());
        $this->assertMatchesRegularExpression('/^box-auto-generated-alias-[\da-zA-Z]{12}\.phar$/', $this->getNoFileConfig()->getAlias());

        $this->setConfig([
            'alias' => null,
        ]);

        $this->assertMatchesRegularExpression('/^box-auto-generated-alias-[\da-zA-Z]{12}\.phar$/', $this->config->getAlias());

        $this->assertSame(
            ['The "alias" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_alias_can_be_configured(): void
    {
        $this->setConfig([
            'alias' => 'test.phar',
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertSame('test.phar', $this->config->getAlias());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_alias_value_is_normalized(): void
    {
        $this->setConfig([
            'alias' => '  test.phar  ',
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertSame('test.phar', $this->config->getAlias());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
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
                $exception->getMessage(),
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
                      - alias : Boolean value found, but a string or a null is required
                    EOF
                ,
                $exception->getMessage(),
            );
        }
    }

    public function test_a_warning_is_given_if_the_alias_has_been_set_but_a_custom_stub_is_provided(): void
    {
        touch('stub-path.php');

        $this->setConfig([
            'alias' => 'test.phar',
            'stub' => 'stub-path.php',
        ]);

        $this->assertSame('test.phar', $this->config->getAlias());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame(
            ['The "alias" setting has been set but is ignored since a custom stub path is used'],
            $this->config->getWarnings(),
        );
    }

    public function test_the_default_base_path_used_is_the_configuration_file_location(): void
    {
        dump_file('sub-dir/box.json', '{}');
        dump_file('sub-dir/index.php');

        $this->file = $this->tmp.'/sub-dir/box.json';

        $this->reloadConfig();

        $this->assertSame($this->tmp.'/sub-dir', $this->config->getBasePath());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
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
            $this->config->getBasePath(),
        );

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_when_the_default_base_path_is_explicitely_used(): void
    {
        $this->setConfig([
            'base-path' => getcwd(),
        ]);

        $this->assertSame(
            getcwd(),
            $this->config->getBasePath(),
        );

        $this->assertSame(
            ['The "base-path" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
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
                $exception->getMessage(),
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
                $exception->getMessage(),
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

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
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

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    /**
     * @dataProvider jsonFilesProvider
     */
    public function test_it_attempts_to_get_and_decode_the_json_and_lock_files(
        callable $setup,
        ?string $expectedJson,
        ?array $expectedJsonContents,
        ?string $expectedLock,
        ?array $expectedLockContents,
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
        $this->assertSame($expectedJsonContents, $this->config->getDecodedComposerJsonContents());

        $this->assertSame($expectedLock, $this->config->getComposerLock());
        $this->assertSame($expectedLockContents, $this->config->getDecodedComposerLockContents());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_it_throws_an_error_when_a_composer_file_is_found_but_invalid(): void
    {
        dump_file('composer.json', '');

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
                $exception->getMessage(),
            );
            $this->assertSame(0, $exception->getCode());
            $this->assertInstanceOf(ParsingException::class, $exception->getPrevious());
        }
    }

    public function test_it_throws_an_error_when_a_composer_lock_is_found_but_invalid(): void
    {
        dump_file('composer.lock', '');

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
                $exception->getMessage(),
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

        $this->assertSame(
            ['The "dump-autoload" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame(
            [
                'The "dump-autoload" setting has been set but has been ignored because the composer.json, composer.lock'
                .' and vendor/composer/installed.json files are necessary but could not be found.',
            ],
            $this->config->getWarnings(),
        );

        dump_file('composer.json', '{}');

        $this->setConfig([]);

        $this->assertTrue($this->config->dumpAutoload());
        $this->assertTrue($this->getNoFileConfig()->dumpAutoload());

        $this->setConfig(['dump-autoload' => null]);

        $this->assertTrue($this->config->dumpAutoload());

        $this->assertSame(
            ['The "dump-autoload" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_autoloader_dumping_can_be_configured(): void
    {
        dump_file('composer.json', '{}');

        $this->setConfig([
            'dump-autoload' => false,
        ]);

        $this->assertFalse($this->config->dumpAutoload());
        $this->assertTrue($this->getNoFileConfig()->dumpAutoload());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'dump-autoload' => true,
        ]);

        $this->assertTrue($this->config->dumpAutoload());
        $this->assertTrue($this->getNoFileConfig()->dumpAutoload());

        $this->assertSame(
            ['The "dump-autoload" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_autoloader_cannot_be_dumped_if_no_composer_json_file_is_found(): void
    {
        $this->setConfig([
            'dump-autoload' => true,
        ]);

        $this->assertFalse($this->config->dumpAutoload());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame(
            [
                'The "dump-autoload" setting has been set but has been ignored because the composer.json, composer.lock'
                .' and vendor/composer/installed.json files are necessary but could not be found.',
            ],
            $this->config->getWarnings(),
        );
    }

    public function test_it_excludes_the_composer_files_by_default(): void
    {
        $this->setConfig([
            'exclude-composer-files' => null,
        ]);

        $this->assertTrue($this->config->excludeComposerFiles());
        $this->assertTrue($this->getNoFileConfig()->excludeComposerFiles());

        $this->assertSame(
            ['The "exclude-composer-files" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'exclude-composer-files' => true,
        ]);

        $this->assertTrue($this->config->excludeComposerFiles());
        $this->assertTrue($this->getNoFileConfig()->excludeComposerFiles());

        $this->assertSame(
            ['The "exclude-composer-files" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_excluding_the_composer_files_can_be_configured(): void
    {
        $this->setConfig([
            'exclude-composer-files' => true,
        ]);

        $this->assertTrue($this->config->excludeComposerFiles());

        $this->assertSame(
            ['The "exclude-composer-files" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'exclude-composer-files' => false,
        ]);

        $this->assertFalse($this->config->excludeComposerFiles());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_no_compactors_is_configured_by_default(): void
    {
        $this->assertSame([], $this->config->getCompactors()->toArray());
        $this->assertSame([], $this->getNoFileConfig()->getCompactors()->toArray());

        $this->setConfig([
            'compactors' => null,
        ]);

        $this->assertSame([], $this->config->getCompactors()->toArray());

        $this->assertSame(
            ['The "compactors" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'compactors' => [],
        ]);

        $this->assertSame([], $this->config->getCompactors()->toArray());

        $this->assertSame(
            ['The "compactors" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
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

        $compactors = $this->config->getCompactors()->toArray();

        $this->assertInstanceOf(Php::class, $compactors[0]);
        $this->assertInstanceOf(DummyCompactor::class, $compactors[1]);
        $this->assertCount(2, $compactors);

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_scoper_compactor_is_registered_before_the_php_compactor(): void
    {
        $compactorClassesSet = [
            [Php::class],
            [PhpScoper::class],
            [
                Php::class,
                PhpScoper::class,
            ],
        ];

        foreach ($compactorClassesSet as $compactorClasses) {
            $this->setConfig([
                'compactors' => $compactorClasses,
            ]);

            $this->assertCount(count($compactorClasses), $this->config->getCompactors()->toArray());

            $this->assertSame([], $this->config->getRecommendations());
            $this->assertSame([], $this->config->getWarnings());
        }

        $this->setConfig([
            'compactors' => [
                PhpScoper::class,
                Php::class,
            ],
        ]);

        $compactors = $this->config->getCompactors()->toArray();

        $this->assertInstanceOf(PhpScoper::class, $compactors[0]);
        $this->assertInstanceOf(Php::class, $compactors[1]);
        $this->assertCount(2, $compactors);

        $this->assertSame(
            ['The PHP compactor has been registered after the PhpScoper compactor. It is recommended to register the PHP compactor before for a clearer code and faster processing.'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
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
                $exception->getMessage(),
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
                    InvalidCompactor::class,
                ),
                $exception->getMessage(),
            );
        }
    }

    public function test_the_php_scoper_configuration_location_can_be_configured(): void
    {
        dump_file('custom.scoper.ini.php', "<?php return ['prefix' => 'custom'];");

        $this->setConfig([
            'php-scoper' => 'custom.scoper.ini.php',
            'compactors' => [
                PhpScoper::class,
            ],
        ]);

        $compactors = $this->config->getCompactors()->toArray();

        $this->assertSame('custom', $compactors[0]->getScoper()->getPrefix());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_default_scoper_path_is_configured_by_default(): void
    {
        dump_file('scoper.inc.php', "<?php return ['prefix' => 'custom'];");

        $this->setConfig([
            'compactors' => [
                PhpScoper::class,
            ],
        ]);

        $compactors = $this->config->getCompactors()->toArray();

        $this->assertSame('custom', $compactors[0]->getScoper()->getPrefix());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'php-scoper' => 'scoper.inc.php',
            'compactors' => [
                PhpScoper::class,
            ],
        ]);

        $compactors = $this->config->getCompactors()->toArray();

        $this->assertSame('custom', $compactors[0]->getScoper()->getPrefix());

        $this->assertSame(
            ['The "php-scoper" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'php-scoper' => null,
            'compactors' => [
                PhpScoper::class,
            ],
        ]);

        $compactors = $this->config->getCompactors()->toArray();

        $this->assertSame('custom', $compactors[0]->getScoper()->getPrefix());

        $this->assertSame(
            ['The "php-scoper" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_no_compression_algorithm_is_configured_by_default(): void
    {
        $this->assertNull($this->config->getCompressionAlgorithm());
        $this->assertNull($this->getNoFileConfig()->getCompressionAlgorithm());

        $this->setConfig([
            'compression' => null,
        ]);

        $this->assertNull($this->config->getCompressionAlgorithm());

        $this->assertSame(
            ['The "compression" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'compression' => 'NONE',
        ]);

        $this->assertNull($this->config->getCompressionAlgorithm());

        $this->assertSame(
            ['The "compression" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_compression_algorithm_with_a_string(): void
    {
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'compression' => 'BZ2',
        ]);

        $this->assertSame(Phar::BZ2, $this->config->getCompressionAlgorithm());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    /**
     * @dataProvider invalidCompressionAlgorithmsProvider
     */
    public function test_the_compression_algorithm_cannot_be_an_invalid_algorithm(mixed $compression, string $errorMessage): void
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
                $exception->getMessage(),
            );
        } catch (JsonValidationException $exception) {
            $this->assertMatchesRegularExpression(
                '/does not match the expected JSON schema:/',
                $exception->getMessage(),
            );
        }
    }

    public function test_a_file_mode_is_configured_by_default(): void
    {
        $this->assertSame(493, $this->config->getFileMode());
        $this->assertSame(493, $this->getNoFileConfig()->getFileMode());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'chmod' => '0755',
        ]);

        $this->assertSame(493, $this->config->getFileMode());

        $this->assertSame(
            ['The "chmod" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'chmod' => '755',
        ]);

        $this->assertSame(493, $this->config->getFileMode());

        $this->assertSame(
            ['The "chmod" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_configure_file_mode(): void
    {
        // Octal value provided
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'chmod' => '0644',
        ]);

        $this->assertSame(420, $this->config->getFileMode());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());

        // Decimal value provided
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'chmod' => '0644',
        ]);

        $this->assertSame(420, $this->config->getFileMode());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_main_script_path_is_configured_by_default(): void
    {
        dump_file('composer.json', '{"bin": []}');

        $this->assertTrue($this->config->hasMainScript());
        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'index.php', $this->config->getMainScriptPath());
        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'index.php', $this->getNoFileConfig()->getMainScriptPath());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
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
                JSON,
        );

        $this->reloadConfig();

        $this->assertTrue($this->config->hasMainScript());
        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'bin/foo', $this->config->getMainScriptPath());

        $this->assertTrue($this->getNoFileConfig()->hasMainScript());
        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'bin/foo', $this->getNoFileConfig()->getMainScriptPath());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
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
                JSON,
        );

        $this->reloadConfig();

        $this->assertTrue($this->config->hasMainScript());
        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'bin/foo', $this->config->getMainScriptPath());
        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'bin/foo', $this->getNoFileConfig()->getMainScriptPath());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_main_script_can_be_configured(): void
    {
        dump_file('test.php', 'Main script contents');

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
                JSON,
        );

        $this->setConfig(['main' => 'test.php']);

        $this->assertTrue($this->config->hasMainScript());
        $this->assertSame($this->tmp.'/test.php', $this->config->getMainScriptPath());
        $this->assertSame('Main script contents', $this->config->getMainScriptContents());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_main_script_path_is_normalized(): void
    {
        touch('test.php');

        $this->setConfig(['main' => ' test.php ']);

        $this->assertSame($this->tmp.'/test.php', $this->config->getMainScriptPath());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_main_script_content_ignores_shebang_line(): void
    {
        dump_file('test.php', "#!/usr/bin/env php\ntest");

        $this->setConfig(['main' => 'test.php']);

        $this->assertSame('test', $this->config->getMainScriptContents());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_it_cannot_get_the_main_script_if_file_does_not_exists(): void
    {
        try {
            $this->setConfig(['main' => 'test.php']);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                "The file \"{$this->tmp}/test.php\" does not exist.",
                $exception->getMessage(),
            );
        }
    }

    public function test_the_main_script_can_be_disabled(): void
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
                JSON,
        );

        $this->setConfig(['main' => false]);

        $this->assertFalse($this->config->hasMainScript());

        try {
            $this->config->getMainScriptPath();

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Cannot retrieve the main script path: no main script configured.',
                $exception->getMessage(),
            );
        }

        try {
            $this->config->getMainScriptContents();

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Cannot retrieve the main script contents: no main script configured.',
                $exception->getMessage(),
            );
        }

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_main_script_cannot_be_enabled(): void
    {
        try {
            $this->setConfig(['main' => true]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Cannot "enable" a main script: either disable it with `false` or give the main script file path.',
                $exception->getMessage(),
            );
        }
    }

    public function test_there_is_no_file_map_configured_by_default(): void
    {
        $mapFile = $this->config->getFileMapper();

        $this->assertSame([], $mapFile->getMap());

        $this->assertSame(
            'first/test/path/sub/path/file.php',
            $mapFile('first/test/path/sub/path/file.php'),
        );

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_when_the_default_map_is_given(): void
    {
        $this->setConfig([
            'map' => [],
        ]);

        $mapFile = $this->config->getFileMapper();

        $this->assertSame([], $mapFile->getMap());

        $this->assertSame(
            'first/test/path/sub/path/file.php',
            $mapFile('first/test/path/sub/path/file.php'),
        );

        $this->assertSame(
            ['The "map" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_file_map_can_be_configured(): void
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
            [
                ['first/test/path' => 'a'],
                ['' => 'b'],
            ],
            $mapFile->getMap(),
        );

        $this->assertSame(
            'a/sub/path/file.php',
            $mapFile('first/test/path/sub/path/file.php'),
        );

        $this->assertSame(
            'b/second/test/path/sub/path/file.php',
            $mapFile('second/test/path/sub/path/file.php'),
        );

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_no_metadata_is_configured_by_default(): void
    {
        $this->assertNull($this->config->getMetadata());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_can_configure_metadata(): void
    {
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'metadata' => 123,
        ]);

        $this->assertSame(123, $this->config->getMetadata());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_default_metadata_is_provided(): void
    {
        $this->setConfig([
            'metadata' => null,
        ]);

        $this->assertNull($this->config->getMetadata());

        $this->assertSame(
            ['The "metadata" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_get_default_output_path(): void
    {
        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'index.phar',
            $this->config->getOutputPath(),
        );
        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'index.phar',
            $this->config->getTmpOutputPath(),
        );

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_output_path_is_configurable(): void
    {
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'output' => 'test.phar',
        ]);

        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test.phar',
            $this->config->getOutputPath(),
        );
        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test.phar',
            $this->config->getTmpOutputPath(),
        );

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_when_the_default_path_is_given(): void
    {
        $this->setConfig([
            'output' => 'index.phar',
        ]);

        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'index.phar',
            $this->config->getOutputPath(),
        );
        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'index.phar',
            $this->config->getTmpOutputPath(),
        );

        $this->assertSame(
            ['The "output" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
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
            $this->config->getOutputPath(),
        );
        $this->assertSame(
            $this->tmp.'/sub-dir/test.phar',
            $this->config->getTmpOutputPath(),
        );

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
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
            $this->config->getOutputPath(),
        );
        $this->assertSame(
            $this->tmp.'/test.phar',
            $this->config->getTmpOutputPath(),
        );

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_output_path_is_normalized(): void
    {
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'output' => ' test.phar ',
        ]);

        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test.phar',
            $this->config->getOutputPath(),
        );
        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test.phar',
            $this->config->getTmpOutputPath(),
        );

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_output_path_can_omit_the_phar_extension(): void
    {
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'output' => 'test',
        ]);

        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test',
            $this->config->getOutputPath(),
        );
        $this->assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test.phar',
            $this->config->getTmpOutputPath(),
        );

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_get_default_output_path_depends_on_the_input(): void
    {
        dump_file('bin/acme');

        $this->setConfig([
            'main' => 'bin/acme',
        ]);

        $this->assertSame(
            $this->tmp.'/bin/acme.phar',
            $this->config->getOutputPath(),
        );

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_no_replacements_are_configured_by_default(): void
    {
        $this->assertSame([], $this->config->getReplacements());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_replacement_map_can_be_configured(): void
    {
        touch('test');
        exec('git init -b main');
        exec('git config user.name "Test User"');
        exec('git config user.email test@test.test');
        exec('git config commit.gpgsign false');
        exec('git add test');
        exec('git commit -m "Adding test file."');
        exec('git tag 1.0.0');

        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'git' => 'git',
            'git-commit' => 'git_commit',
            'git-commit-short' => 'git_commit_short',
            'git-tag' => 'git_tag',
            'git-version' => 'git_version',
            'replacements' => ['rand' => $rand = random_int(0, mt_getrandmax())],
            'datetime' => 'date_time',
            'datetime-format' => 'Y:m:d',
        ]);

        $values = $this->config->getReplacements();

        $this->assertSame('1.0.0', $values['@git@']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $values['@git_commit@']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{7}$/', $values['@git_commit_short@']);
        $this->assertSame('1.0.0', $values['@git_tag@']);
        $this->assertSame('1.0.0', $values['@git_version@']);
        $this->assertSame($rand, $values['@rand@']);
        $this->assertMatchesRegularExpression(
            '/^[0-9]{4}:[0-9]{2}:[0-9]{2}$/',
            $values['@date_time@'],
        );
        $this->assertCount(7, $values);

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());

        touch('foo');
        exec('git add foo');
        exec('git commit -m "Adding another test file."');

        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'git' => 'git',
            'git-commit' => 'git_commit',
            'git-commit-short' => 'git_commit_short',
            'git-tag' => 'git_tag',
            'git-version' => 'git_version',
            'replacements' => ['rand' => $rand = random_int(0, mt_getrandmax())],
            'replacement-sigil' => '$',
            'datetime' => 'date_time',
            'datetime-format' => 'Y:m:d',
        ]);

        $values = $this->config->getReplacements();

        $this->assertMatchesRegularExpression('/^.+@[a-f0-9]{7}$/', $values['$git$']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $values['$git_commit$']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{7}$/', $values['$git_commit_short$']);
        $this->assertMatchesRegularExpression('/^.+-\d+-g[a-f0-9]{7}$/', $values['$git_tag$']);
        $this->assertMatchesRegularExpression('/^.+-\d+-g[a-f0-9]{7}$/', $values['$git_version$']);
        $this->assertSame($rand, $values['$rand$']);
        $this->assertMatchesRegularExpression(
            '/^[0-9]{4}:[0-9]{2}:[0-9]{2}$/',
            $values['$date_time$'],
        );
        $this->assertCount(7, $values);

        // Some process does not release the git files
        if ($this->isWindows()) {
            exec('rd /S /Q .git');
        }

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_replacement_map_can_be_configured_when_base_path_is_different_directory(): void
    {
        // Make another directory level to have config not in base-path.
        $basePath = $this->tmp.DIRECTORY_SEPARATOR.'subdir';
        mkdir($basePath);
        rename(self::DEFAULT_FILE, $basePath.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);
        chdir($basePath);
        touch('test');
        exec('git init -b main');
        exec('git config user.name "Test User"');
        exec('git config user.email test@test.test');
        exec('git config commit.gpgsign false');
        exec('git add test');
        exec('git commit -m "Adding test file."');
        exec('git tag 1.0.0');
        chdir($this->tmp);

        $this->setConfig([
            'base-path' => $basePath,
            'files' => [self::DEFAULT_FILE],
            'git' => 'git',
            'git-commit' => 'git_commit',
            'git-commit-short' => 'git_commit_short',
            'git-tag' => 'git_tag',
            'git-version' => 'git_version',
        ]);

        $values = $this->config->getReplacements();

        $this->assertSame('1.0.0', $values['@git@']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $values['@git_commit@']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{7}$/', $values['@git_commit_short@']);
        $this->assertSame('1.0.0', $values['@git_tag@']);
        $this->assertSame('1.0.0', $values['@git_version@']);
        $this->assertCount(5, $values);

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());

        chdir($basePath);
        touch('foo');
        exec('git add foo');
        exec('git commit -m "Adding another test file."');
        chdir($this->tmp);

        $this->setConfig([
            'base-path' => $basePath,
            'files' => [self::DEFAULT_FILE],
            'git' => 'git',
            'git-commit' => 'git_commit',
            'git-commit-short' => 'git_commit_short',
            'git-tag' => 'git_tag',
            'git-version' => 'git_version',
        ]);

        $values = $this->config->getReplacements();

        $this->assertMatchesRegularExpression('/^.+@[a-f0-9]{7}$/', $values['@git@']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $values['@git_commit@']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{7}$/', $values['@git_commit_short@']);
        $this->assertMatchesRegularExpression('/^.+-\d+-g[a-f0-9]{7}$/', $values['@git_tag@']);
        $this->assertMatchesRegularExpression('/^.+-\d+-g[a-f0-9]{7}$/', $values['@git_version@']);
        $this->assertCount(5, $values);

        // Some process does not release the git files
        if ($this->isWindows()) {
            exec('rd /S /Q .git');
        }

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_default_replacements_setting_is_provided(): void
    {
        $this->setConfig([
            'replacements' => new stdClass(),
        ]);

        $this->assertSame([], $this->config->getReplacements());

        $this->assertSame(
            ['The "replacements" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_datetime_replacement_has_a_default_date_format(): void
    {
        $this->setConfig(['datetime' => 'date_time']);

        $this->assertMatchesRegularExpression(
            '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2} [A-Z]{2,5}$/',
            $this->config->getReplacements()['@date_time@'],
        );
        $this->assertCount(1, $this->config->getReplacements());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_datetime_is_converted_to_utc(): void
    {
        date_default_timezone_set('UTC');

        $now = new DateTimeImmutable();

        date_default_timezone_set('Asia/Tokyo');

        $this->setConfig(['datetime' => 'date_time']);

        date_default_timezone_set('UTC');

        $configDateTime = new DateTimeImmutable($this->config->getReplacements()['@date_time@']);

        $this->assertLessThan(10, abs($configDateTime->getTimestamp() - $now->getTimestamp()));

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_datetime_format_must_be_valid(): void
    {
        try {
            $this->setConfig(['datetime-format' => 'ü']);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Expected the datetime format to be a valid format: "ü" is not',
                $exception->getMessage(),
            );
        }
    }

    /**
     * @group legacy
     */
    public function test_the_new_datetime_format_setting_takes_precedence_over_the_old_one(): void
    {
        $this->setConfig([
            'datetime' => 'date_time',
            'datetime_format' => 'Y:m:d',
            'datetime-format' => 'Y-m-d',
        ]);

        $values = $this->config->getReplacements();

        $this->assertMatchesRegularExpression(
            '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',
            $values['@date_time@'],
        );

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_replacement_sigil_can_be_a_chain_of_characters(): void
    {
        $this->setConfig([
            'replacements' => ['foo' => 'bar'],
            'replacement-sigil' => '__',
        ]);

        $this->assertSame(
            ['__foo__' => 'bar'],
            $this->config->getReplacements(),
        );

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_config_has_a_default_shebang(): void
    {
        $this->assertSame('#!/usr/bin/env php', $this->config->getShebang());

        $this->setConfig([
            'shebang' => null,
        ]);

        $this->assertSame('#!/usr/bin/env php', $this->config->getShebang());

        $this->assertSame(
            ['The "shebang" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_shebang_can_be_configured(): void
    {
        $this->setConfig([
            'shebang' => $expected = '#!/bin/php',
            'files' => [self::DEFAULT_FILE],
        ]);

        $actual = $this->config->getShebang();

        $this->assertSame($expected, $actual);

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_shebang_configured_to_its_default_value(): void
    {
        $this->setConfig([
            'shebang' => '#!/usr/bin/env php',
        ]);

        $this->assertSame('#!/usr/bin/env php', $this->config->getShebang());

        $this->assertSame(
            ['The "shebang" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_warning_is_given_if_the_shebang_configured_but_a_custom_stub_is_used(): void
    {
        touch('my-stub.php');

        $this->setConfig([
            'shebang' => $expected = '#!/bin/php',
            'stub' => 'my-stub.php',
        ]);

        $this->assertSame($expected, $this->config->getShebang());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame(
            ['The "shebang" has been set but ignored since it is used only with the Box built-in stub which is not used'],
            $this->config->getWarnings(),
        );
    }

    public function test_a_warning_is_given_if_the_shebang_configured_but_the_phar_default_stub_is_used(): void
    {
        $this->setConfig([
            'shebang' => $expected = '#!/bin/php',
            'stub' => false,
        ]);

        $this->assertSame($expected, $this->config->getShebang());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame(
            ['The "shebang" has been set but ignored since it is used only with the Box built-in stub which is not used'],
            $this->config->getWarnings(),
        );
    }

    public function test_a_recommendation_is_given_if_the_shebang_disabled_and_a_custom_stub_is_used(): void
    {
        touch('my-stub.php');

        $this->setConfig([
            'shebang' => false,
            'stub' => 'my-stub.php',
        ]);

        $this->assertNull($this->config->getShebang());

        $this->assertSame(
            ['The "shebang" has been set to `false` but is unnecessary since the Box built-in stub is not being used'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_shebang_disabled_and_the_phar_default_stub_is_used(): void
    {
        $this->setConfig([
            'shebang' => false,
            'stub' => false,
        ]);

        $this->assertNull($this->config->getShebang());

        $this->assertSame(
            ['The "shebang" has been set to `false` but is unnecessary since the Box built-in stub is not being used'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_it_cannot_retrieve_the_git_hash_if_not_in_a_git_repository(): void
    {
        try {
            $this->setConfig([
                'git' => 'git',
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            $tmp = $this->tmp;

            // Make the comparison case insensitive since depending of the git version the case may be different
            $this->assertSame(
                strtolower(
                    sprintf(
                        'The tag or commit hash could not be retrieved from "%s": fatal: Not a git repository '
                        .'(or any of the parent directories): .git'.PHP_EOL,
                        $tmp,
                    ),
                ),
                strtolower($exception->getMessage()),
            );
        }
    }

    public function test_a_recommendation_is_given_if_the_configured_git_placeholder_is_the_default_value(): void
    {
        $this->setConfig([
            'git' => null,
        ]);

        $this->assertSame([], $this->config->getReplacements());

        $this->assertSame(
            ['The "git" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_configured_git_commit_placeholder_is_the_default_value(): void
    {
        $this->setConfig([
            'git-commit' => null,
        ]);

        $this->assertSame([], $this->config->getReplacements());

        $this->assertSame(
            ['The "git-commit" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_configured_git_short_hash_placeholder_is_the_default_value(): void
    {
        $this->setConfig([
            'git-commit-short' => null,
        ]);

        $this->assertSame([], $this->config->getReplacements());

        $this->assertSame(
            ['The "git-commit-short" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_configured_git_tag_placeholder_is_the_default_value(): void
    {
        $this->setConfig([
            'git-tag' => null,
        ]);

        $this->assertSame([], $this->config->getReplacements());

        $this->assertSame(
            ['The "git-tag" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_configured_git_version_placeholder_is_the_default_value(): void
    {
        $this->setConfig([
            'git-version' => null,
        ]);

        $this->assertSame([], $this->config->getReplacements());

        $this->assertSame(
            ['The "git-version" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_configured_datetime_format_is_the_default_value(): void
    {
        $this->setConfig([
            'datetime-format' => 'Y-m-d H:i:s T',
        ]);

        $this->assertSame([], $this->config->getReplacements());

        $this->assertSame(
            [
                'The "datetime-format" setting can be omitted since is set to its default value',
                'The setting "datetime-format" has been set but is unnecessary because the setting "datetime" is not set.',
            ],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_configured_datetime_is_the_default_value(): void
    {
        $this->setConfig([
            'datetime' => null,
        ]);

        $this->assertSame([], $this->config->getReplacements());

        $this->assertSame(
            ['The "datetime" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_datetime_format_is_configured_but_no_datetime_placeholder_is_not_provided(): void
    {
        $this->setConfig([
            'datetime-format' => 'Y-m-d H:i',
        ]);

        $this->assertSame([], $this->config->getReplacements());

        $this->assertSame(
            ['The setting "datetime-format" has been set but is unnecessary because the setting "datetime" is not set.'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_configured_replacement_sigil_is_the_default_value(): void
    {
        $this->setConfig([
            'replacement-sigil' => null,
        ]);

        $this->assertSame([], $this->config->getReplacements());

        $this->assertSame(
            ['The "replacement-sigil" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_shebang_can_be_disabled(): void
    {
        $this->setConfig([
            'shebang' => false,
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertNull($this->config->getShebang());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_shebang_is_the_default_value(): void
    {
        $this->setConfig([
            'shebang' => null,
        ]);

        $this->assertSame('#!/usr/bin/env php', $this->config->getShebang());

        $this->assertSame(
            ['The "shebang" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'shebang' => '#!/usr/bin/env php',
        ]);

        $this->assertSame('#!/usr/bin/env php', $this->config->getShebang());

        $this->assertSame(
            ['The "shebang" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
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
                $exception->getMessage(),
            );
        }

        try {
            $this->setConfig([
                'shebang' => true,
                'files' => [self::DEFAULT_FILE],
            ]);

            $this->fail('Expected exception ot be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Expected shebang to be either a string, false or null, found true',
                $exception->getMessage(),
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
                $exception->getMessage(),
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
                $exception->getMessage(),
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

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_there_is_a_banner_registered_by_default(): void
    {
        $version = self::$version;

        $expected = <<<BANNER
            Generated by Humbug Box $version.

            @link https://github.com/humbug/box
            BANNER;

        $this->assertSame($expected, $this->config->getStubBannerContents());
        $this->assertNull($this->config->getStubBannerPath());

        $this->setConfig([
            'banner' => null,
            'files' => [self::DEFAULT_FILE],
        ]);

        $expected = <<<BANNER
            Generated by Humbug Box $version.

            @link https://github.com/humbug/box
            BANNER;

        $this->assertSame($expected, $this->config->getStubBannerContents());
        $this->assertNull($this->config->getStubBannerPath());

        $this->assertSame(
            ['The "banner" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_banner_is_the_default_value(): void
    {
        $this->setConfig([
            'banner' => null,
        ]);

        $this->assertSame(
            ['The "banner" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());

        $version = self::$version;

        $this->setConfig([
            'banner' => <<<BANNER
                Generated by Humbug Box $version.

                @link https://github.com/humbug/box
                BANNER
            ,
        ]);

        $this->assertSame(
            ['The "banner" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    /**
     * @dataProvider customBannerProvider
     */
    public function test_a_custom_banner_can_be_registered(string $banner): void
    {
        $this->setConfig([
            'banner' => $banner,
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertSame($banner, $this->config->getStubBannerContents());
        $this->assertNull($this->config->getStubBannerPath());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_warning_is_given_when_the_banner_is_configured_but_the_box_stub_is_not_used(): void
    {
        touch('my-stub.php');

        foreach (['my-stub.php', false] as $stub) {
            $this->setConfig([
                'banner' => 'custom banner',
                'stub' => $stub,
            ]);

            $this->assertSame('custom banner', $this->config->getStubBannerContents());
            $this->assertNull($this->config->getStubBannerPath());

            $this->assertSame([], $this->config->getRecommendations());
            $this->assertSame(
                ['The "banner" setting has been set but is ignored since the Box built-in stub is not being used'],
                $this->config->getWarnings(),
            );
        }
    }

    public function test_a_recommendation_is_given_when_the_banner_is_disabled_but_the_box_stub_is_not_used(): void
    {
        touch('my-stub.php');

        foreach (['my-stub.php', false] as $stub) {
            $this->setConfig([
                'banner' => false,
                'stub' => $stub,
            ]);

            $this->assertNull($this->config->getStubBannerContents());
            $this->assertNull($this->config->getStubBannerPath());

            $this->assertSame(
                ['The "banner" setting has been set but is unnecessary since the Box built-in stub is not being used'],
                $this->config->getRecommendations(),
            );
            $this->assertSame([], $this->config->getWarnings());
        }
    }

    public function test_the_banner_can_be_disabled(): void
    {
        $this->setConfig([
            'banner' => false,
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertNull($this->config->getStubBannerContents());
        $this->assertNull($this->config->getStubBannerPath());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_banner_must_be_a_valid_value(): void
    {
        try {
            $this->setConfig([
                'banner' => true,
                'files' => [self::DEFAULT_FILE],
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The banner cannot accept true as a value',
                $exception->getMessage(),
            );
        }
    }

    /**
     * @dataProvider unormalizedCustomBannerProvider
     */
    public function test_the_content_of_the_banner_is_normalized(string $banner, string $expected): void
    {
        $this->setConfig([
            'banner' => $banner,
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertSame($expected, $this->config->getStubBannerContents());
        $this->assertNull($this->config->getStubBannerPath());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
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

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_not_banner_path_is_registered_by_default(): void
    {
        $this->assertNull($this->getNoFileConfig()->getStubBannerPath());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'banner-file' => null,
        ]);

        $this->assertNull($this->config->getStubBannerPath());

        $this->assertSame(
            ['The "banner-file" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_custom_banner_from_a_file_can_be_registered(): void
    {
        $comment = <<<'COMMENT'
            This is a

            multiline

            comment.
            COMMENT;

        dump_file('banner', $comment);

        $this->setConfig([
            'banner-file' => 'banner',
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertSame($comment, $this->config->getStubBannerContents());
        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'banner', $this->config->getStubBannerPath());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_default_stub_banner_path_is_configured(): void
    {
        $version = self::$version;

        $defaultBanner = <<<BANNER
            Generated by Humbug Box $version.

            @link https://github.com/humbug/box
            BANNER;

        $this->setConfig([
            'banner-file' => null,
        ]);

        $this->assertSame($defaultBanner, $this->config->getStubBannerContents());
        $this->assertNull($this->config->getStubBannerPath());

        $this->assertSame(
            ['The "banner-file" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());

        dump_file('custom-banner', $defaultBanner);

        $this->setConfig([
            'banner-file' => 'custom-banner',
        ]);

        $this->assertSame($defaultBanner, $this->config->getStubBannerContents());
        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'custom-banner', $this->config->getStubBannerPath());

        $this->assertSame(
            ['The "banner-file" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());

        $version = self::$version;

        dump_file(
            'custom-banner',
            <<<BANNER
                  Generated by Humbug Box $version.

                  @link https://github.com/humbug/box
                BANNER,
        );

        $this->setConfig([
            'banner-file' => 'custom-banner',
        ]);

        $this->assertSame($defaultBanner, $this->config->getStubBannerContents());
        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'custom-banner', $this->config->getStubBannerPath());

        $this->assertSame(
            ['The "banner-file" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_warning_is_given_when_the_banner_file_is_configured_but_the_box_stub_is_not_used(): void
    {
        touch('custom-banner');
        touch('my-stub.php');

        foreach (['my-stub.php', false] as $stub) {
            $this->setConfig([
                'banner-file' => 'custom-banner',
                'stub' => $stub,
            ]);

            $this->assertSame('', $this->config->getStubBannerContents());
            $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'custom-banner', $this->config->getStubBannerPath());

            $this->assertSame([], $this->config->getRecommendations());
            $this->assertSame(
                ['The "banner-file" setting has been set but is ignored since the Box built-in stub is not being used'],
                $this->config->getWarnings(),
            );
        }
    }

    public function test_the_banner_value_is_discarded_if_a_banner_file_is_registered(): void
    {
        $comment = <<<'COMMENT'
            This is a

            multiline

            comment.
            COMMENT;

        dump_file('banner', $comment);

        $this->setConfig([
            'banner' => 'discarded banner',
            'banner-file' => 'banner',
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertSame($comment, $this->config->getStubBannerContents());
        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'banner', $this->config->getStubBannerPath());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
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

        dump_file('banner', $comment);

        $this->setConfig([
            'banner-file' => 'banner',
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertSame($expected, $this->config->getStubBannerContents());
        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'banner', $this->config->getStubBannerPath());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
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
                'The file "/does/not/exist" does not exist.',
                $exception->getMessage(),
            );
        }
    }

    public function test_by_default_there_is_no_stub_and_the_stub_is_generated(): void
    {
        $this->assertNull($this->config->getStubPath());
        $this->assertTrue($this->config->isStubGenerated());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'stub' => null,
        ]);

        $this->assertNull($this->config->getStubPath());
        $this->assertTrue($this->config->isStubGenerated());

        $this->assertSame(
            ['The "stub" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'stub' => true,
        ]);

        $this->assertNull($this->config->getStubPath());
        $this->assertTrue($this->config->isStubGenerated());

        $this->assertSame(
            ['The "stub" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_custom_stub_can_be_provided(): void
    {
        dump_file('custom-stub.php', '');

        $this->setConfig([
            'stub' => 'custom-stub.php',
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'custom-stub.php', $this->config->getStubPath());
        $this->assertFalse($this->config->isStubGenerated());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_stub_can_be_generated(): void
    {
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertNull($this->config->getStubPath());
        $this->assertTrue($this->config->isStubGenerated());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());

        foreach ([true, null] as $stub) {
            $this->setConfig([
                'stub' => $stub,
                'files' => [self::DEFAULT_FILE],
            ]);

            $this->assertNull($this->config->getStubPath());
            $this->assertTrue($this->config->isStubGenerated());
        }
    }

    public function test_the_default_stub_can_be_used(): void
    {
        $this->setConfig([
            'stub' => false,
            'files' => [self::DEFAULT_FILE],
        ]);

        $this->assertNull($this->config->getStubPath());
        $this->assertFalse($this->config->isStubGenerated());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_funcs_are_not_intercepted_by_default(): void
    {
        $this->assertFalse($this->config->isInterceptFileFuncs());

        $this->setConfig([
            'intercept' => null,
        ]);

        $this->assertFalse($this->config->isInterceptFileFuncs());

        $this->assertSame(
            ['The "intercept" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'intercept' => false,
        ]);

        $this->assertFalse($this->config->isInterceptFileFuncs());

        $this->assertSame(
            ['The "intercept" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_intercept_funcs_can_be_enabled(): void
    {
        $this->setConfig([
            'intercept' => true,
        ]);

        $this->assertTrue($this->config->isInterceptFileFuncs());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_warning_is_given_when_the_intercept_funcs_is_configured_but_the_box_stub_is_not_used(): void
    {
        touch('my-stub.php');

        foreach (['my-stub.php', false] as $stub) {
            $this->setConfig([
                'intercept' => true,
                'stub' => $stub,
            ]);

            $this->assertTrue($this->config->isInterceptFileFuncs());

            $this->assertSame([], $this->config->getRecommendations());
            $this->assertSame(
                ['The "intercept" setting has been set but is ignored since the Box built-in stub is not being used'],
                $this->config->getWarnings(),
            );
        }
    }

    public function test_a_recommendation_is_given_when_the_intercept_funcs_is_disabled_but_the_box_stub_is_not_used(): void
    {
        touch('my-stub.php');

        foreach (['my-stub.php', false] as $stub) {
            $this->setConfig([
                'intercept' => false,
                'stub' => $stub,
            ]);

            $this->assertFalse($this->config->isInterceptFileFuncs());

            $this->assertSame(
                ['The "intercept" setting can be omitted since is set to its default value'],
                $this->config->getRecommendations(),
            );
            $this->assertSame([], $this->config->getWarnings());
        }
    }

    public function test_the_requirement_checker_can_be_disabled(): void
    {
        $this->setConfig([
            'check-requirements' => false,
        ]);

        $this->assertFalse($this->config->checkRequirements());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());

        dump_file('composer.lock', '{}');

        $this->reloadConfig();

        $this->assertFalse($this->config->checkRequirements());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_requirement_checker_is_enabled_by_default_if_a_composer_lock_or_json_file_is_found(): void
    {
        $this->assertFalse($this->config->checkRequirements());

        dump_file('composer.lock', '{}');

        $this->reloadConfig();

        $this->assertTrue($this->config->checkRequirements());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());

        dump_file('composer.json', '{}');
        remove('composer.lock');

        $this->reloadConfig();

        $this->assertTrue($this->config->checkRequirements());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());

        dump_file('composer.lock', '{}');

        $this->reloadConfig();

        $this->assertTrue($this->config->checkRequirements());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_requirement_checker_can_be_enabled(): void
    {
        dump_file('composer.json', '{}');
        dump_file('composer.lock', '{}');
        dump_file('vendor/composer/installed.json', '{}');

        $this->setConfig([
            'check-requirements' => true,
        ]);

        $this->assertTrue($this->config->checkRequirements());

        $this->assertSame(
            ['The "check-requirements" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_requirement_checker_is_forcibly_disabled_if_the_composer_files_could_not_be_found(): void
    {
        $this->setConfig([
            'check-requirements' => true,
        ]);

        $this->assertFalse($this->config->checkRequirements());

        $this->assertSame(
            ['The "check-requirements" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame(
            ['The requirement checker could not be used because the composer.json and composer.lock file could not be found.'],
            $this->config->getWarnings(),
        );
    }

    public function test_a_recommendation_is_given_if_the_default_check_requirement_value_is_given(): void
    {
        $this->setConfig([
            'check-requirements' => null,
        ]);

        $this->assertFalse($this->config->checkRequirements());

        $this->assertSame(
            ['The "check-requirements" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame(
            ['The requirement checker could not be used because the composer.json and composer.lock file could not be found.'],
            $this->config->getWarnings(),
        );

        $this->setConfig([
            'check-requirements' => true,
        ]);

        $this->assertFalse($this->config->checkRequirements());

        $this->assertSame(
            ['The "check-requirements" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame(
            ['The requirement checker could not be used because the composer.json and composer.lock file could not be found.'],
            $this->config->getWarnings(),
        );

        dump_file('composer.json', '{}');
        dump_file('composer.lock', '{}');
        dump_file('vendor/composer/installed.json', '{}');

        $this->setConfig([
            'check-requirements' => null,
        ]);

        $this->assertTrue($this->config->checkRequirements());

        $this->assertSame(
            ['The "check-requirements" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'check-requirements' => true,
        ]);

        $this->assertTrue($this->config->checkRequirements());

        $this->assertSame(
            ['The "check-requirements" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_warning_is_given_if_the_check_requirement_is_configured_but_the_phar_stub_used(): void
    {
        dump_file('composer.json', '{}');
        dump_file('composer.lock', '{}');
        dump_file('vendor/composer/installed.json', '{}');

        $this->setConfig([
            'check-requirements' => true,
            'stub' => false,
        ]);

        $this->assertTrue($this->config->checkRequirements());

        $this->assertSame(
            ['The "check-requirements" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame(
            ['The "check-requirements" setting has been set but has been ignored since the PHAR built-in stub is being used.'],
            $this->config->getWarnings(),
        );
    }

    public function test_by_default_dev_files_are_excluded_if_dump_autoload_is_enabled(): void
    {
        // Those checks are somewhat redundant. They are however done here to make sure the exclude dev files
        // checks stay in sync with the dump-autoload
        $this->assertFalse($this->config->dumpAutoload());
        $this->assertFalse($this->getNoFileConfig()->dumpAutoload());

        $this->assertFalse($this->config->excludeDevFiles());
        $this->assertFalse($this->getNoFileConfig()->excludeDevFiles());

        $this->setConfig(['exclude-dev-files' => null]);

        $this->assertFalse($this->config->excludeDevFiles());

        $this->assertSame(
            ['The "exclude-dev-files" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());

        dump_file('composer.json', '{}');

        $this->setConfig([]);

        $this->assertTrue($this->config->dumpAutoload());
        $this->assertTrue($this->getNoFileConfig()->dumpAutoload());

        $this->assertTrue($this->config->excludeDevFiles());
        $this->assertTrue($this->getNoFileConfig()->excludeDevFiles());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());

        $this->setConfig(['exclude-dev-files' => null]);

        $this->assertTrue($this->config->dumpAutoload());
        $this->assertTrue($this->config->excludeDevFiles());

        $this->assertSame(
            ['The "exclude-dev-files" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'dump-autoload' => false,
        ]);

        $this->assertFalse($this->config->dumpAutoload());
        $this->assertTrue($this->getNoFileConfig()->dumpAutoload());

        $this->assertFalse($this->config->excludeDevFiles());
        $this->assertTrue($this->getNoFileConfig()->excludeDevFiles());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'dump-autoload' => true,
        ]);

        $this->assertTrue($this->config->dumpAutoload());
        $this->assertTrue($this->getNoFileConfig()->dumpAutoload());

        $this->assertTrue($this->config->excludeDevFiles());
        $this->assertTrue($this->getNoFileConfig()->excludeDevFiles());

        $this->assertSame(
            ['The "dump-autoload" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_exclude_dev_files_is_enabled_when_the_autoload_is_dumped(): void
    {
        dump_file('composer.json', '{}');

        $this->setConfig([
            'dump-autoload' => true,
            'exclude-dev-files' => true,
        ]);

        $this->assertTrue($this->config->dumpAutoload());
        $this->assertTrue($this->getNoFileConfig()->dumpAutoload());

        $this->assertTrue($this->config->excludeDevFiles());
        $this->assertTrue($this->getNoFileConfig()->excludeDevFiles());

        $this->assertSame(
            [
                'The "dump-autoload" setting can be omitted since is set to its default value',
                'The "exclude-dev-files" setting can be omitted since is set to its default value',
            ],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_exclude_dev_files_is_disabled_when_the_autoload_is_not_dumped(): void
    {
        dump_file('composer.json', '{}');

        $this->setConfig([
            'dump-autoload' => false,
            'exclude-dev-files' => false,
        ]);

        $this->assertFalse($this->config->dumpAutoload());
        $this->assertTrue($this->getNoFileConfig()->dumpAutoload());

        $this->assertFalse($this->config->excludeDevFiles());
        $this->assertTrue($this->getNoFileConfig()->excludeDevFiles());

        $this->assertSame(
            ['The "exclude-dev-files" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_dev_files_can_be_not_excluded_when_the_autoloader_is_dumped(): void
    {
        dump_file('composer.json', '{}');

        $this->setConfig([
            'dump-autoload' => true,
            'exclude-dev-files' => false,
        ]);

        $this->assertTrue($this->config->dumpAutoload());
        $this->assertTrue($this->getNoFileConfig()->dumpAutoload());

        $this->assertFalse($this->config->excludeDevFiles());
        $this->assertTrue($this->getNoFileConfig()->excludeDevFiles());

        $this->assertSame(
            ['The "dump-autoload" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_a_warning_is_given_if_dev_files_are_explicitly_excluded_but_the_autoloader_not_dumped(): void
    {
        dump_file('composer.json', '{}');

        $this->setConfig([
            'dump-autoload' => false,
            'exclude-dev-files' => true,
        ]);

        $this->assertFalse($this->config->dumpAutoload());
        $this->assertTrue($this->getNoFileConfig()->dumpAutoload());

        $this->assertFalse($this->config->excludeDevFiles());
        $this->assertTrue($this->getNoFileConfig()->excludeDevFiles());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame(
            ['The "exclude-dev-files" setting has been set but has been ignored because the Composer autoloader is not dumped'],
            $this->config->getWarnings(),
        );
    }

    public function test_it_can_be_created_with_only_default_values(): void
    {
        $this->setConfig(
            array_fill_keys(
                $this->retrieveSchemaKeys(),
                null,
            ),
        );

        $this->assertFalse($this->config->checkRequirements());
        $this->assertFalse($this->config->dumpAutoload());
        $this->assertTrue($this->config->excludeComposerFiles());
        $this->assertMatchesRegularExpression('/^box-auto-generated-alias-[\da-zA-Z]{12}\.phar$/', $this->config->getAlias());
        $this->assertSame($this->tmp, $this->config->getBasePath());
        $this->assertSame([], $this->config->getBinaryFiles());
        $this->assertSame([], $this->config->getCompactors()->toArray());
        $this->assertFalse($this->config->hasAutodiscoveredFiles());
        $this->assertNull($this->config->getComposerJson());
        $this->assertNull($this->config->getComposerLock());
        $this->assertNull($this->config->getCompressionAlgorithm());
        $this->assertNull($this->config->getDecodedComposerJsonContents());
        $this->assertNull($this->config->getDecodedComposerLockContents());
        $this->assertSame($this->tmp.'/box.json', $this->config->getConfigurationFile());
        $this->assertEquals(
            new MapFile($this->tmp, []),
            $this->config->getFileMapper(),
        );
        $this->assertSame(493, $this->config->getFileMode());
        $this->assertSame([], $this->config->getFiles());
        $this->assertSame('', $this->config->getMainScriptContents());
        $this->assertSame($this->tmp.'/index.php', $this->config->getMainScriptPath());
        $this->assertNull($this->config->getMetadata());
        $this->assertSame($this->tmp.'/index.phar', $this->config->getOutputPath());
        $this->assertNull($this->config->getPrivateKeyPassphrase());
        $this->assertNull($this->config->getPrivateKeyPath());
        $this->assertSame([], $this->config->getReplacements());
        $this->assertSame('#!/usr/bin/env php', $this->config->getShebang());
        $this->assertSame(Phar::SHA1, $this->config->getSigningAlgorithm());

        $version = self::$version;

        $this->assertSame(
            <<<BANNER
                Generated by Humbug Box $version.

                @link https://github.com/humbug/box
                BANNER
            ,
            $this->config->getStubBannerContents(),
        );
        $this->assertNull($this->config->getStubPath());
        $this->assertSame($this->tmp.'/index.phar', $this->config->getTmpOutputPath());
        $this->assertTrue($this->config->hasMainScript());
        $this->assertFalse($this->config->isInterceptFileFuncs());
        $this->assertFalse($this->config->promptForPrivateKey());
        $this->assertTrue($this->config->isStubGenerated());
    }

    public function test_it_can_be_exported(): void
    {
        touch('foo.php');
        touch('bar.php');

        dump_file(
            'composer.json',
            <<<'JSON'
                {
                    "config": {
                        "bin-dir": "bin",
                        "platform": {
                            "php": "7.1.10"
                        },
                        "sort-packages": true
                    }
                }
                JSON,
        );
        dump_file('composer.lock', '{}');
        dump_file('vendor/composer/installed.json', '{}');

        $this->setConfig([
            'alias' => 'test.phar',
            'banner' => 'My banner',
            'files-bin' => [
                'foo.php',
                'bar.php',  // comes second to see if the sorting from the export kicks in correctly
            ],
            'compactors' => [Php::class],
            'compression' => 'GZ',
        ]);

        $expectedDumpedConfig = <<<EOF
            KevinGH\Box\Configuration\Configuration {#100
              -compressionAlgorithm: "GZ"
              -mainScriptPath: "index.php"
              -mainScriptContents: ""
              -file: "box.json"
              -alias: "test.phar"
              -basePath: "/path/to"
              -composerJson: KevinGH\Box\Composer\ComposerFile {#100
                -path: "composer.json"
                -contents: array:1 [
                  "config" => array:3 [
                    "bin-dir" => "bin"
                    "platform" => array:1 [
                      "php" => "7.1.10"
                    ]
                    "sort-packages" => true
                  ]
                ]
              }
              -composerLock: KevinGH\Box\Composer\ComposerFile {#100
                -path: "composer.lock"
                -contents: []
              }
              -files: array:6 [
                0 => "bar.php"
                1 => "box.json"
                2 => "composer.json"
                3 => "composer.lock"
                4 => "foo.php"
                5 => "vendor/composer/installed.json"
              ]
              -binaryFiles: array:2 [
                0 => "bar.php"
                1 => "foo.php"
              ]
              -autodiscoveredFiles: true
              -dumpAutoload: true
              -excludeComposerFiles: true
              -excludeDevFiles: true
              -compactors: array:1 [
                0 => "KevinGH\Box\Compactor\Php"
              ]
              -fileMode: "0755"
              -fileMapper: KevinGH\Box\MapFile {#100
                -basePath: "/path/to"
                -map: []
              }
              -metadata: null
              -tmpOutputPath: "index.phar"
              -outputPath: "index.phar"
              -privateKeyPassphrase: null
              -privateKeyPath: null
              -promptForPrivateKey: false
              -processedReplacements: []
              -shebang: "#!/usr/bin/env php"
              -signingAlgorithm: "SHA1"
              -stubBannerContents: "My banner"
              -stubBannerPath: null
              -stubPath: null
              -isInterceptFileFuncs: false
              -isStubGenerated: true
              -checkRequirements: true
              -warnings: []
              -recommendations: []
            }

            EOF;

        $actualDumpedConfig = VarDumperNormalizer::normalize(
            $this->tmp,
            $this->config->export(),
        );

        $actualDumpedConfig = preg_replace(
            '/ \{#\d{2,}/',
            ' {#100',
            $actualDumpedConfig,
        );

        $this->assertSame($expectedDumpedConfig, $actualDumpedConfig);
    }

    public static function invalidCompressionAlgorithmsProvider(): iterable
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

    public static function JsonValidNonStringValuesProvider(): iterable
    {
        foreach (self::provideJsonPrimitives() as $key => $value) {
            if ('string' === $key) {
                continue;
            }

            yield $key => [$value];
        }
    }

    public static function JsonValidNonStringArrayProvider(): iterable
    {
        foreach (self::provideJsonPrimitives() as $key => $values) {
            if ('string' === $key) {
                continue;
            }

            yield $key.'[]' => [[$values]];
        }
    }

    public static function JsonValidNonObjectArrayProvider(): iterable
    {
        foreach (self::provideJsonPrimitives() as $key => $values) {
            if ('object' === $key) {
                continue;
            }

            yield $key.'[]' => [[$values]];
        }
    }

    public static function provideJsonPrimitives(): iterable
    {
        yield 'null' => null;
        yield 'bool' => true;
        yield 'number' => 30;
        yield 'string' => 'foo';
        yield 'object' => ['foo' => 'bar'];
        yield 'array' => ['foo', 'bar'];
    }

    public static function customBannerProvider(): iterable
    {
        yield ['Simple banner'];

        yield [<<<'COMMENT'
            This is a

            multiline

            banner.
            COMMENT
        ];
    }

    public static function unormalizedCustomBannerProvider(): iterable
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
                COMMENT,
        ];
    }

    public static function jsonFilesProvider(): iterable
    {
        yield [
            static function (): void {},
            null,
            null,
            null,
            null,
        ];

        yield [
            static function (): void {
                dump_file('composer.json', '{}');
            },
            'composer.json',
            [],
            null,
            null,
        ];

        yield [
            static function (): void {
                dump_file('composer.json', '{"name": "acme/foo"}');
            },
            'composer.json',
            ['name' => 'acme/foo'],
            null,
            null,
        ];

        yield [
            static function (): void {
                dump_file('composer.lock', '{}');
            },
            null,
            null,
            'composer.lock',
            [],
        ];

        yield [
            static function (): void {
                dump_file('composer.lock', '{"name": "acme/foo"}');
            },
            null,
            null,
            'composer.lock',
            ['name' => 'acme/foo'],
        ];

        yield [
            static function (): void {
                dump_file('composer.json', '{"name": "acme/foo"}');
                dump_file('composer.lock', '{"name": "acme/bar"}');
            },
            'composer.json',
            ['name' => 'acme/foo'],
            'composer.lock',
            ['name' => 'acme/bar'],
        ];

        yield [
            static function (): void {
                mkdir('composer.json');
            },
            null,
            null,
            null,
            null,
        ];

        yield [
            static function (): void {
                mkdir('composer.lock');
            },
            null,
            null,
            null,
            null,
        ];

        yield [
            static function (): void {
                touch('composer.json');
                chmod('composer.json', 0000);
            },
            null,
            null,
            null,
            null,
        ];

        yield [
            static function (): void {
                touch('composer.lock');
                chmod('composer.lock', 0000);
            },
            null,
            null,
            null,
            null,
        ];
    }

    public static function PassFileFreeSigningAlgorithmProvider(): iterable
    {
        yield ['MD5', Phar::MD5];
        yield ['SHA1', Phar::SHA1];
        yield ['SHA256', Phar::SHA256];
        yield ['SHA512', Phar::SHA512];
    }

    /**
     * @return string[]
     */
    private function retrieveSchemaKeys(): array
    {
        $schema = json_decode(
            file_contents(__DIR__.'/../../res/schema.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return array_keys($schema['properties']);
    }
}
