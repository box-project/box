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

use DateTimeImmutable;
use DateTimeInterface;
use Fidry\FileSystem\FS;
use InvalidArgumentException;
use JsonException;
use KevinGH\Box\Compactor\InvalidCompactor;
use KevinGH\Box\Compactor\NullCompactor;
use KevinGH\Box\Compactor\Php;
use KevinGH\Box\Compactor\PhpScoper;
use KevinGH\Box\Composer\Artifact\ComposerJson;
use KevinGH\Box\Composer\Artifact\ComposerLock;
use KevinGH\Box\Json\JsonValidationException;
use KevinGH\Box\MapFile;
use KevinGH\Box\Phar\CompressionAlgorithm;
use KevinGH\Box\Phar\SigningAlgorithm;
use KevinGH\Box\VarDumperNormalizer;
use Phar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use stdClass;
use function abs;
use function array_fill_keys;
use function array_keys;
use function count;
use function date_default_timezone_set;
use function exec;
use function getcwd;
use function json_decode;
use function KevinGH\Box\get_box_version;
use function mt_getrandmax;
use function random_int;
use function sprintf;
use function strtr;
use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;

/**
 * @internal
 */
#[CoversClass(Configuration::class)]
#[CoversClass(MapFile::class)]
#[Group('config')]
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

        self::assertSame('box.json', $config->getConfigurationFile());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_it_can_be_created_without_a_file(): void
    {
        $config = Configuration::create(null, new stdClass());

        self::assertNull($config->getConfigurationFile());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_default_alias_is_generated_if_no_alias_is_registered(): void
    {
        self::assertMatchesRegularExpression('/^box-auto-generated-alias-[\da-zA-Z]{12}\.phar$/', $this->config->getAlias());
        self::assertMatchesRegularExpression('/^box-auto-generated-alias-[\da-zA-Z]{12}\.phar$/', $this->getNoFileConfig()->getAlias());

        $this->setConfig([
            'alias' => null,
        ]);

        self::assertMatchesRegularExpression('/^box-auto-generated-alias-[\da-zA-Z]{12}\.phar$/', $this->config->getAlias());

        self::assertSame(
            ['The "alias" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_alias_can_be_configured(): void
    {
        $this->setConfig([
            'alias' => 'test.phar',
            'files' => [self::DEFAULT_FILE],
        ]);

        self::assertSame('test.phar', $this->config->getAlias());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_alias_value_is_normalized(): void
    {
        $this->setConfig([
            'alias' => '  test.phar  ',
            'files' => [self::DEFAULT_FILE],
        ]);

        self::assertSame('test.phar', $this->config->getAlias());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_alias_cannot_be_empty(): void
    {
        try {
            $this->setConfig([
                'alias' => '',
                'files' => [self::DEFAULT_FILE],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
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

            self::fail('Expected exception to be thrown.');
        } catch (JsonValidationException $exception) {
            self::assertSame(
                <<<EOF
                    "{$this->file}" does not match the expected JSON schema:
                      - alias : Boolean value found, but a string or a null is required
                    EOF,
                $exception->getMessage(),
            );
        }
    }

    public function test_a_warning_is_given_if_the_alias_has_been_set_but_a_custom_stub_is_provided(): void
    {
        FS::touch('stub-path.php');

        $this->setConfig([
            'alias' => 'test.phar',
            'stub' => 'stub-path.php',
        ]);

        self::assertSame('test.phar', $this->config->getAlias());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame(
            ['The "alias" setting has been set but is ignored since a custom stub path is used'],
            $this->config->getWarnings(),
        );
    }

    public function test_the_default_base_path_used_is_the_configuration_file_location(): void
    {
        FS::dumpFile('sub-dir/box.json', '{}');
        FS::dumpFile('sub-dir/index.php');

        $this->file = $this->tmp.'/sub-dir/box.json';

        $this->reloadConfig();

        self::assertSame($this->tmp.'/sub-dir', $this->config->getBasePath());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_if_there_is_no_file_the_default_base_path_used_is_the_current_working_directory(): void
    {
        self::assertSame($this->tmp, $this->getNoFileConfig()->getBasePath());
    }

    public function test_the_base_path_can_be_configured(): void
    {
        FS::mkdir($basePath = $this->tmp.DIRECTORY_SEPARATOR.'test');
        FS::rename(self::DEFAULT_FILE, $basePath.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        $this->setConfig([
            'base-path' => $basePath,
        ]);

        self::assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test',
            $this->config->getBasePath(),
        );

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_when_the_default_base_path_is_explicitely_used(): void
    {
        $this->setConfig([
            'base-path' => getcwd(),
        ]);

        self::assertSame(
            getcwd(),
            $this->config->getBasePath(),
        );

        self::assertSame(
            ['The "base-path" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_non_existent_directory_cannot_be_used_as_a_base_path(): void
    {
        try {
            $this->setConfig([
                'base-path' => $this->tmp.DIRECTORY_SEPARATOR.'test',
                'files' => [self::DEFAULT_FILE],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'The base path "'.$this->tmp.DIRECTORY_SEPARATOR.'test" is not a directory or does not exist.',
                $exception->getMessage(),
            );
        }
    }

    public function test_a_file_path_cannot_be_used_as_a_base_path(): void
    {
        FS::touch('foo');

        try {
            $this->setConfig([
                'base-path' => $this->tmp.DIRECTORY_SEPARATOR.'foo',
                'files' => [self::DEFAULT_FILE],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'The base path "'.$this->tmp.DIRECTORY_SEPARATOR.'foo" is not a directory or does not exist.',
                $exception->getMessage(),
            );
        }
    }

    public function test_if_the_base_path_is_relative_then_it_is_relative_to_the_current_working_directory(): void
    {
        FS::mkdir('dir');
        FS::rename(self::DEFAULT_FILE, 'dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        $this->setConfig([
            'base-path' => 'dir',
        ]);

        $expected = $this->tmp.DIRECTORY_SEPARATOR.'dir';

        self::assertSame($expected, $this->config->getBasePath());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_base_path_value_is_normalized(): void
    {
        FS::mkdir('dir');
        FS::rename(self::DEFAULT_FILE, 'dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        $this->setConfig([
            'base-path' => ' dir ',
        ]);

        $expected = $this->tmp.DIRECTORY_SEPARATOR.'dir';

        self::assertSame($expected, $this->config->getBasePath());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    #[DataProvider('composerArtifactsProvider')]
    public function test_it_attempts_to_get_and_decode_the_json_and_lock_files(
        callable $setup,
        ?string $expectedJsonPath,
        ?array $expectedJsonContents,
        ?string $expectedLockPath,
        ?array $expectedLockContents,
    ): void {
        $setup();

        $expectedJson = null === $expectedJsonPath
            ? null
            : new ComposerJson(
                $this->tmp.DIRECTORY_SEPARATOR.$expectedJsonPath,
                $expectedJsonContents ?? [],
            );

        $expectedLock = null === $expectedLockPath
            ? null
            : new ComposerLock(
                $this->tmp.DIRECTORY_SEPARATOR.$expectedLockPath,
                $expectedLockContents ?? [],
            );

        $this->reloadConfig();

        self::assertEquals($expectedJson, $this->config->getComposerJson());
        self::assertEquals($expectedLock, $this->config->getComposerLock());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_it_throws_an_error_when_a_composer_file_is_found_but_invalid(): void
    {
        FS::dumpFile('composer.json');

        try {
            $this->reloadConfig();
        } catch (InvalidArgumentException $exception) {
            $composerJson = $this->tmp.'/composer.json';

            self::assertStringStartsWith(
                "Expected the file \"{$composerJson}\" to be a valid JSON file but an error has been found: ",
                $exception->getMessage(),
            );
            self::assertSame(0, $exception->getCode());
            self::assertInstanceOf(JsonException::class, $exception->getPrevious());
        }
    }

    public function test_it_throws_an_error_when_a_composer_lock_is_found_but_invalid(): void
    {
        FS::dumpFile('composer.lock');

        try {
            $this->reloadConfig();

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $composerLock = $this->tmp.'/composer.lock';

            self::assertStringStartsWith(
                "Expected the file \"{$composerLock}\" to be a valid JSON file but an error has been found: ",
                $exception->getMessage(),
            );
            self::assertSame(0, $exception->getCode());
            self::assertInstanceOf(JsonException::class, $exception->getPrevious());
        }
    }

    public function test_the_autoloader_is_dumped_by_default_if_a_composer_json_file_is_found(): void
    {
        self::assertFalse($this->config->dumpAutoload());
        self::assertFalse($this->getNoFileConfig()->dumpAutoload());

        $this->setConfig(['dump-autoload' => null]);

        self::assertFalse($this->config->dumpAutoload());

        self::assertSame(
            ['The "dump-autoload" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame(
            [
                'The "dump-autoload" setting has been set but has been ignored because the composer.json, composer.lock'
                .' and vendor/composer/installed.json files are necessary but could not be found.',
            ],
            $this->config->getWarnings(),
        );

        FS::dumpFile('composer.json', '{}');

        $this->setConfig([]);

        self::assertTrue($this->config->dumpAutoload());
        self::assertTrue($this->getNoFileConfig()->dumpAutoload());

        $this->setConfig(['dump-autoload' => null]);

        self::assertTrue($this->config->dumpAutoload());

        self::assertSame(
            ['The "dump-autoload" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_autoloader_dumping_can_be_configured(): void
    {
        FS::dumpFile('composer.json', '{}');

        $this->setConfig([
            'dump-autoload' => false,
        ]);

        self::assertFalse($this->config->dumpAutoload());
        self::assertTrue($this->getNoFileConfig()->dumpAutoload());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'dump-autoload' => true,
        ]);

        self::assertTrue($this->config->dumpAutoload());
        self::assertTrue($this->getNoFileConfig()->dumpAutoload());

        self::assertSame(
            ['The "dump-autoload" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_autoloader_cannot_be_dumped_if_no_composer_json_file_is_found(): void
    {
        $this->setConfig([
            'dump-autoload' => true,
        ]);

        self::assertFalse($this->config->dumpAutoload());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame(
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

        self::assertTrue($this->config->excludeComposerArtifacts());
        self::assertTrue($this->getNoFileConfig()->excludeComposerArtifacts());

        self::assertSame(
            ['The "exclude-composer-files" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'exclude-composer-files' => true,
        ]);

        self::assertTrue($this->config->excludeComposerArtifacts());
        self::assertTrue($this->getNoFileConfig()->excludeComposerArtifacts());

        self::assertSame(
            ['The "exclude-composer-files" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_excluding_the_composer_files_can_be_configured(): void
    {
        $this->setConfig([
            'exclude-composer-files' => true,
        ]);

        self::assertTrue($this->config->excludeComposerArtifacts());

        self::assertSame(
            ['The "exclude-composer-files" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'exclude-composer-files' => false,
        ]);

        self::assertFalse($this->config->excludeComposerArtifacts());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_no_compactors_is_configured_by_default(): void
    {
        self::assertSame([], $this->config->getCompactors()->toArray());
        self::assertSame([], $this->getNoFileConfig()->getCompactors()->toArray());

        $this->setConfig([
            'compactors' => null,
        ]);

        self::assertSame([], $this->config->getCompactors()->toArray());

        self::assertSame(
            ['The "compactors" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'compactors' => [],
        ]);

        self::assertSame([], $this->config->getCompactors()->toArray());

        self::assertSame(
            ['The "compactors" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_configure_the_compactors(): void
    {
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'compactors' => [
                Php::class,
                NullCompactor::class,
            ],
        ]);

        $compactors = $this->config->getCompactors()->toArray();

        self::assertInstanceOf(Php::class, $compactors[0]);
        self::assertInstanceOf(NullCompactor::class, $compactors[1]);
        self::assertCount(2, $compactors);

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
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

            self::assertCount(count($compactorClasses), $this->config->getCompactors()->toArray());

            self::assertSame([], $this->config->getRecommendations());
            self::assertSame([], $this->config->getWarnings());
        }

        $this->setConfig([
            'compactors' => [
                PhpScoper::class,
                Php::class,
            ],
        ]);

        $compactors = $this->config->getCompactors()->toArray();

        self::assertInstanceOf(PhpScoper::class, $compactors[0]);
        self::assertInstanceOf(Php::class, $compactors[1]);
        self::assertCount(2, $compactors);

        self::assertSame(
            ['The PHP compactor has been registered after the PhpScoper compactor. It is recommended to register the PHP compactor before for a clearer code and faster processing.'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_it_cannot_get_the_compactors_with_an_invalid_class(): void
    {
        try {
            $this->setConfig([
                'files' => [self::DEFAULT_FILE],
                'compactors' => ['NoSuchClass'],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
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

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
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
        FS::dumpFile('custom.scoper.ini.php', "<?php return ['prefix' => 'custom'];");

        $this->setConfig([
            'php-scoper' => 'custom.scoper.ini.php',
            'compactors' => [
                PhpScoper::class,
            ],
        ]);

        $compactors = $this->config->getCompactors()->toArray();

        self::assertSame('custom', $compactors[0]->getScoper()->getPrefix());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_default_scoper_path_is_configured_by_default(): void
    {
        FS::dumpFile('scoper.inc.php', "<?php return ['prefix' => 'custom'];");

        $this->setConfig([
            'compactors' => [
                PhpScoper::class,
            ],
        ]);

        $compactors = $this->config->getCompactors()->toArray();

        self::assertSame('custom', $compactors[0]->getScoper()->getPrefix());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'php-scoper' => 'scoper.inc.php',
            'compactors' => [
                PhpScoper::class,
            ],
        ]);

        $compactors = $this->config->getCompactors()->toArray();

        self::assertSame('custom', $compactors[0]->getScoper()->getPrefix());

        self::assertSame(
            ['The "php-scoper" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'php-scoper' => null,
            'compactors' => [
                PhpScoper::class,
            ],
        ]);

        $compactors = $this->config->getCompactors()->toArray();

        self::assertSame('custom', $compactors[0]->getScoper()->getPrefix());

        self::assertSame(
            ['The "php-scoper" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_no_compression_algorithm_is_configured_by_default(): void
    {
        self::assertSame(CompressionAlgorithm::NONE, $this->config->getCompressionAlgorithm());
        self::assertSame(CompressionAlgorithm::NONE, $this->getNoFileConfig()->getCompressionAlgorithm());

        $this->setConfig([
            'compression' => null,
        ]);

        self::assertSame(CompressionAlgorithm::NONE, $this->config->getCompressionAlgorithm());

        self::assertSame(
            ['The "compression" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'compression' => 'NONE',
        ]);

        self::assertSame(CompressionAlgorithm::NONE, $this->config->getCompressionAlgorithm());

        self::assertSame(
            ['The "compression" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_compression_algorithm_with_a_string(): void
    {
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'compression' => 'BZ2',
        ]);

        self::assertSame(CompressionAlgorithm::BZ2, $this->config->getCompressionAlgorithm());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    #[DataProvider('invalidCompressionAlgorithmsProvider')]
    public function test_the_compression_algorithm_cannot_be_an_invalid_algorithm(mixed $compression, string $errorMessage): void
    {
        try {
            $this->setConfig([
                'files' => [self::DEFAULT_FILE],
                'compression' => $compression,
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                $errorMessage,
                $exception->getMessage(),
            );
        } catch (JsonValidationException $exception) {
            self::assertMatchesRegularExpression(
                '/does not match the expected JSON schema:/',
                $exception->getMessage(),
            );
        }
    }

    public function test_a_file_mode_is_configured_by_default(): void
    {
        self::assertSame(493, $this->config->getFileMode());
        self::assertSame(493, $this->getNoFileConfig()->getFileMode());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'chmod' => '0755',
        ]);

        self::assertSame(493, $this->config->getFileMode());

        self::assertSame(
            ['The "chmod" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'chmod' => '755',
        ]);

        self::assertSame(493, $this->config->getFileMode());

        self::assertSame(
            ['The "chmod" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_configure_file_mode(): void
    {
        // Octal value provided
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'chmod' => '0644',
        ]);

        self::assertSame(420, $this->config->getFileMode());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());

        // Decimal value provided
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'chmod' => '0644',
        ]);

        self::assertSame(420, $this->config->getFileMode());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_main_script_path_is_configured_by_default(): void
    {
        FS::dumpFile('composer.json', '{"bin": []}');

        self::assertTrue($this->config->hasMainScript());
        self::assertSame($this->tmp.DIRECTORY_SEPARATOR.'index.php', $this->config->getMainScriptPath());
        self::assertSame($this->tmp.DIRECTORY_SEPARATOR.'index.php', $this->getNoFileConfig()->getMainScriptPath());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_main_script_path_is_inferred_by_the_composer_json_by_default(): void
    {
        FS::dumpFile('bin/foo');

        FS::dumpFile(
            'composer.json',
            <<<'JSON'
                {
                    "bin": "bin/foo"
                }
                JSON,
        );

        $this->reloadConfig();

        self::assertTrue($this->config->hasMainScript());
        self::assertSame($this->tmp.DIRECTORY_SEPARATOR.'bin/foo', $this->config->getMainScriptPath());

        self::assertTrue($this->getNoFileConfig()->hasMainScript());
        self::assertSame($this->tmp.DIRECTORY_SEPARATOR.'bin/foo', $this->getNoFileConfig()->getMainScriptPath());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_first_composer_bin_is_used_as_the_main_script_by_default(): void
    {
        FS::dumpFile('bin/foo');
        FS::dumpFile('bin/bar');

        FS::dumpFile(
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

        self::assertTrue($this->config->hasMainScript());
        self::assertSame($this->tmp.DIRECTORY_SEPARATOR.'bin/foo', $this->config->getMainScriptPath());
        self::assertSame($this->tmp.DIRECTORY_SEPARATOR.'bin/foo', $this->getNoFileConfig()->getMainScriptPath());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_main_script_can_be_configured(): void
    {
        FS::dumpFile('test.php', 'Main script contents');

        FS::dumpFile('bin/foo');
        FS::dumpFile('bin/bar');

        FS::dumpFile(
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

        self::assertTrue($this->config->hasMainScript());
        self::assertSame($this->tmp.'/test.php', $this->config->getMainScriptPath());
        self::assertSame('Main script contents', $this->config->getMainScriptContents());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_main_script_path_is_normalized(): void
    {
        FS::touch('test.php');

        $this->setConfig(['main' => ' test.php ']);

        self::assertSame($this->tmp.'/test.php', $this->config->getMainScriptPath());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_main_script_content_ignores_shebang_line(): void
    {
        FS::dumpFile('test.php', "#!/usr/bin/env php\ntest");

        $this->setConfig(['main' => 'test.php']);

        self::assertSame('test', $this->config->getMainScriptContents());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_it_cannot_get_the_main_script_if_file_does_not_exists(): void
    {
        try {
            $this->setConfig(['main' => 'test.php']);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                "The file \"{$this->tmp}/test.php\" does not exist.",
                $exception->getMessage(),
            );
        }
    }

    public function test_the_main_script_can_be_disabled(): void
    {
        FS::dumpFile('bin/foo');
        FS::dumpFile('bin/bar');

        FS::dumpFile(
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

        self::assertFalse($this->config->hasMainScript());

        try {
            $this->config->getMainScriptPath();

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Cannot retrieve the main script path: no main script configured.',
                $exception->getMessage(),
            );
        }

        try {
            $this->config->getMainScriptContents();

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Cannot retrieve the main script contents: no main script configured.',
                $exception->getMessage(),
            );
        }

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_main_script_cannot_be_enabled(): void
    {
        try {
            $this->setConfig(['main' => true]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Cannot "enable" a main script: either disable it with `false` or give the main script file path.',
                $exception->getMessage(),
            );
        }
    }

    public function test_there_is_no_file_map_configured_by_default(): void
    {
        $mapFile = $this->config->getFileMapper();

        self::assertSame([], $mapFile->getMap());

        self::assertSame(
            'first/test/path/sub/path/file.php',
            $mapFile('first/test/path/sub/path/file.php'),
        );

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_when_the_default_map_is_given(): void
    {
        $this->setConfig([
            'map' => [],
        ]);

        $mapFile = $this->config->getFileMapper();

        self::assertSame([], $mapFile->getMap());

        self::assertSame(
            'first/test/path/sub/path/file.php',
            $mapFile('first/test/path/sub/path/file.php'),
        );

        self::assertSame(
            ['The "map" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
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

        self::assertSame(
            [
                ['first/test/path' => 'a'],
                ['' => 'b'],
            ],
            $mapFile->getMap(),
        );

        self::assertSame(
            'a/sub/path/file.php',
            $mapFile('first/test/path/sub/path/file.php'),
        );

        self::assertSame(
            'b/second/test/path/sub/path/file.php',
            $mapFile('second/test/path/sub/path/file.php'),
        );

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_no_metadata_is_configured_by_default(): void
    {
        self::assertNull($this->config->getMetadata());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_can_configure_metadata(): void
    {
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'metadata' => 123,
        ]);

        self::assertSame(123, $this->config->getMetadata());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame(
            [
                'Using the "metadata" setting is deprecated and will be removed in 5.0.0.',
            ],
            $this->config->getWarnings(),
        );
    }

    public function test_a_recommendation_is_given_if_the_default_metadata_is_provided(): void
    {
        $this->setConfig([
            'metadata' => null,
        ]);

        self::assertNull($this->config->getMetadata());

        self::assertSame(
            ['The "metadata" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_get_default_output_path(): void
    {
        self::assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'index.phar',
            $this->config->getOutputPath(),
        );
        self::assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'index.phar',
            $this->config->getTmpOutputPath(),
        );

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_output_path_is_configurable(): void
    {
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'output' => 'test.phar',
        ]);

        self::assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test.phar',
            $this->config->getOutputPath(),
        );
        self::assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test.phar',
            $this->config->getTmpOutputPath(),
        );

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_when_the_default_path_is_given(): void
    {
        $this->setConfig([
            'output' => 'index.phar',
        ]);

        self::assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'index.phar',
            $this->config->getOutputPath(),
        );
        self::assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'index.phar',
            $this->config->getTmpOutputPath(),
        );

        self::assertSame(
            ['The "output" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_output_path_is_relative_to_the_base_path(): void
    {
        FS::mkdir('sub-dir');
        FS::rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        $this->setConfig([
            'output' => 'test.phar',
            'base-path' => 'sub-dir',
        ]);

        self::assertSame(
            $this->tmp.'/sub-dir/test.phar',
            $this->config->getOutputPath(),
        );
        self::assertSame(
            $this->tmp.'/sub-dir/test.phar',
            $this->config->getTmpOutputPath(),
        );

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_output_path_is_not_relative_to_the_base_path_if_is_absolute(): void
    {
        FS::mkdir('sub-dir');
        FS::rename(self::DEFAULT_FILE, 'sub-dir'.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);

        $this->setConfig([
            'output' => $this->tmp.'/test.phar',
            'base-path' => 'sub-dir',
        ]);

        self::assertSame(
            $this->tmp.'/test.phar',
            $this->config->getOutputPath(),
        );
        self::assertSame(
            $this->tmp.'/test.phar',
            $this->config->getTmpOutputPath(),
        );

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_output_path_is_normalized(): void
    {
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'output' => ' test.phar ',
        ]);

        self::assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test.phar',
            $this->config->getOutputPath(),
        );
        self::assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test.phar',
            $this->config->getTmpOutputPath(),
        );

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_output_path_can_omit_the_phar_extension(): void
    {
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
            'output' => 'test',
        ]);

        self::assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test',
            $this->config->getOutputPath(),
        );
        self::assertSame(
            $this->tmp.DIRECTORY_SEPARATOR.'test.phar',
            $this->config->getTmpOutputPath(),
        );

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_get_default_output_path_depends_on_the_input(): void
    {
        FS::dumpFile('bin/acme');

        $this->setConfig([
            'main' => 'bin/acme',
        ]);

        self::assertSame(
            $this->tmp.'/bin/acme.phar',
            $this->config->getOutputPath(),
        );

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_no_replacements_are_configured_by_default(): void
    {
        self::assertSame([], $this->config->getReplacements());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_replacement_map_can_be_configured(): void
    {
        FS::touch('test');
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

        self::assertSame('1.0.0', $values['@git@']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $values['@git_commit@']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{7}$/', $values['@git_commit_short@']);
        self::assertSame('1.0.0', $values['@git_tag@']);
        self::assertSame('1.0.0', $values['@git_version@']);
        self::assertSame($rand, $values['@rand@']);
        self::assertMatchesRegularExpression(
            '/^[0-9]{4}:[0-9]{2}:[0-9]{2}$/',
            $values['@date_time@'],
        );
        self::assertCount(7, $values);

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());

        FS::touch('foo');
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

        self::assertMatchesRegularExpression('/^.+@[a-f0-9]{7}$/', $values['$git$']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $values['$git_commit$']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{7}$/', $values['$git_commit_short$']);
        self::assertMatchesRegularExpression('/^.+-\d+-g[a-f0-9]{7}$/', $values['$git_tag$']);
        self::assertMatchesRegularExpression('/^.+-\d+-g[a-f0-9]{7}$/', $values['$git_version$']);
        self::assertSame($rand, $values['$rand$']);
        self::assertMatchesRegularExpression(
            '/^[0-9]{4}:[0-9]{2}:[0-9]{2}$/',
            $values['$date_time$'],
        );
        self::assertCount(7, $values);

        // Some process does not release the git files
        if ($this->isWindows()) {
            exec('rd /S /Q .git');
        }

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_replacement_map_can_be_configured_when_base_path_is_different_directory(): void
    {
        // Make another directory level to have config not in base-path.
        $basePath = $this->tmp.DIRECTORY_SEPARATOR.'subdir';
        FS::mkdir($basePath);
        FS::rename(self::DEFAULT_FILE, $basePath.DIRECTORY_SEPARATOR.self::DEFAULT_FILE);
        chdir($basePath);
        FS::touch('test');
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

        self::assertSame('1.0.0', $values['@git@']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $values['@git_commit@']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{7}$/', $values['@git_commit_short@']);
        self::assertSame('1.0.0', $values['@git_tag@']);
        self::assertSame('1.0.0', $values['@git_version@']);
        self::assertCount(5, $values);

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());

        chdir($basePath);
        FS::touch('foo');
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

        self::assertMatchesRegularExpression('/^.+@[a-f0-9]{7}$/', $values['@git@']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $values['@git_commit@']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{7}$/', $values['@git_commit_short@']);
        self::assertMatchesRegularExpression('/^.+-\d+-g[a-f0-9]{7}$/', $values['@git_tag@']);
        self::assertMatchesRegularExpression('/^.+-\d+-g[a-f0-9]{7}$/', $values['@git_version@']);
        self::assertCount(5, $values);

        // Some process does not release the git files
        if ($this->isWindows()) {
            exec('rd /S /Q .git');
        }

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_default_replacements_setting_is_provided(): void
    {
        $this->setConfig([
            'replacements' => new stdClass(),
        ]);

        self::assertSame([], $this->config->getReplacements());

        self::assertSame(
            ['The "replacements" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_datetime_replacement_has_a_default_date_format(): void
    {
        $this->setConfig(['datetime' => 'date_time']);

        self::assertMatchesRegularExpression(
            '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2} [A-Z]{2,5}$/',
            $this->config->getReplacements()['@date_time@'],
        );
        self::assertCount(1, $this->config->getReplacements());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_datetime_is_converted_to_utc(): void
    {
        date_default_timezone_set('UTC');

        $now = new DateTimeImmutable();

        date_default_timezone_set('Asia/Tokyo');

        $this->setConfig(['datetime' => 'date_time']);

        date_default_timezone_set('UTC');

        $configDateTime = new DateTimeImmutable($this->config->getReplacements()['@date_time@']);

        self::assertLessThan(10, abs($configDateTime->getTimestamp() - $now->getTimestamp()));

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_datetime_format_must_be_valid(): void
    {
        try {
            $this->setConfig(['datetime-format' => 'ü']);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Expected the datetime format to be a valid format: "ü" is not',
                $exception->getMessage(),
            );
        }
    }

    #[Group('legacy')]
    public function test_the_new_datetime_format_setting_takes_precedence_over_the_old_one(): void
    {
        $this->setConfig([
            'datetime' => 'date_time',
            'datetime_format' => 'Y:m:d',
            'datetime-format' => 'Y-m-d',
        ]);

        $values = $this->config->getReplacements();

        self::assertMatchesRegularExpression(
            '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',
            $values['@date_time@'],
        );

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_replacement_sigil_can_be_a_chain_of_characters(): void
    {
        $this->setConfig([
            'replacements' => ['foo' => 'bar'],
            'replacement-sigil' => '__',
        ]);

        self::assertSame(
            ['__foo__' => 'bar'],
            $this->config->getReplacements(),
        );

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_config_has_a_default_shebang(): void
    {
        self::assertSame('#!/usr/bin/env php', $this->config->getShebang());

        $this->setConfig([
            'shebang' => null,
        ]);

        self::assertSame('#!/usr/bin/env php', $this->config->getShebang());

        self::assertSame(
            ['The "shebang" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_shebang_can_be_configured(): void
    {
        $this->setConfig([
            'shebang' => $expected = '#!/bin/php',
            'files' => [self::DEFAULT_FILE],
        ]);

        $actual = $this->config->getShebang();

        self::assertSame($expected, $actual);

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_shebang_configured_to_its_default_value(): void
    {
        $this->setConfig([
            'shebang' => '#!/usr/bin/env php',
        ]);

        self::assertSame('#!/usr/bin/env php', $this->config->getShebang());

        self::assertSame(
            ['The "shebang" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_warning_is_given_if_the_shebang_configured_but_a_custom_stub_is_used(): void
    {
        FS::touch('my-stub.php');

        $this->setConfig([
            'shebang' => $expected = '#!/bin/php',
            'stub' => 'my-stub.php',
        ]);

        self::assertSame($expected, $this->config->getShebang());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame(
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

        self::assertSame($expected, $this->config->getShebang());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame(
            ['The "shebang" has been set but ignored since it is used only with the Box built-in stub which is not used'],
            $this->config->getWarnings(),
        );
    }

    public function test_a_recommendation_is_given_if_the_shebang_disabled_and_a_custom_stub_is_used(): void
    {
        FS::touch('my-stub.php');

        $this->setConfig([
            'shebang' => false,
            'stub' => 'my-stub.php',
        ]);

        self::assertNull($this->config->getShebang());

        self::assertSame(
            ['The "shebang" has been set to `false` but is unnecessary since the Box built-in stub is not being used'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_shebang_disabled_and_the_phar_default_stub_is_used(): void
    {
        $this->setConfig([
            'shebang' => false,
            'stub' => false,
        ]);

        self::assertNull($this->config->getShebang());

        self::assertSame(
            ['The "shebang" has been set to `false` but is unnecessary since the Box built-in stub is not being used'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_it_cannot_retrieve_the_git_hash_if_not_in_a_git_repository(): void
    {
        $regex = strtr(
            '~^The tag or commit hash could not be retrieved from "{path}": fatal: Not a git repository~i',
            ['{path}' => $this->tmp],
        );

        $this->expectExceptionMessageMatches($regex);
        $this->expectException(RuntimeException::class);

        $this->setConfig(['git' => 'git']);
    }

    public function test_a_recommendation_is_given_if_the_configured_git_placeholder_is_the_default_value(): void
    {
        $this->setConfig([
            'git' => null,
        ]);

        self::assertSame([], $this->config->getReplacements());

        self::assertSame(
            ['The "git" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_configured_git_commit_placeholder_is_the_default_value(): void
    {
        $this->setConfig([
            'git-commit' => null,
        ]);

        self::assertSame([], $this->config->getReplacements());

        self::assertSame(
            ['The "git-commit" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_configured_git_short_hash_placeholder_is_the_default_value(): void
    {
        $this->setConfig([
            'git-commit-short' => null,
        ]);

        self::assertSame([], $this->config->getReplacements());

        self::assertSame(
            ['The "git-commit-short" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_configured_git_tag_placeholder_is_the_default_value(): void
    {
        $this->setConfig([
            'git-tag' => null,
        ]);

        self::assertSame([], $this->config->getReplacements());

        self::assertSame(
            ['The "git-tag" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_configured_git_version_placeholder_is_the_default_value(): void
    {
        $this->setConfig([
            'git-version' => null,
        ]);

        self::assertSame([], $this->config->getReplacements());

        self::assertSame(
            ['The "git-version" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_configured_datetime_format_is_the_default_value(): void
    {
        $this->setConfig([
            'datetime-format' => 'Y-m-d H:i:s T',
        ]);

        self::assertSame([], $this->config->getReplacements());

        self::assertSame(
            [
                'The "datetime-format" setting can be omitted since is set to its default value',
                'The setting "datetime-format" has been set but is unnecessary because the setting "datetime" is not set.',
            ],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_configured_datetime_is_the_default_value(): void
    {
        $this->setConfig([
            'datetime' => null,
        ]);

        self::assertSame([], $this->config->getReplacements());

        self::assertSame(
            ['The "datetime" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_datetime_format_is_configured_but_no_datetime_placeholder_is_not_provided(): void
    {
        $this->setConfig([
            'datetime-format' => 'Y-m-d H:i',
        ]);

        self::assertSame([], $this->config->getReplacements());

        self::assertSame(
            ['The setting "datetime-format" has been set but is unnecessary because the setting "datetime" is not set.'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_configured_replacement_sigil_is_the_default_value(): void
    {
        $this->setConfig([
            'replacement-sigil' => null,
        ]);

        self::assertSame([], $this->config->getReplacements());

        self::assertSame(
            ['The "replacement-sigil" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_shebang_can_be_disabled(): void
    {
        $this->setConfig([
            'shebang' => false,
            'files' => [self::DEFAULT_FILE],
        ]);

        self::assertNull($this->config->getShebang());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_shebang_is_the_default_value(): void
    {
        $this->setConfig([
            'shebang' => null,
        ]);

        self::assertSame('#!/usr/bin/env php', $this->config->getShebang());

        self::assertSame(
            ['The "shebang" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'shebang' => '#!/usr/bin/env php',
        ]);

        self::assertSame('#!/usr/bin/env php', $this->config->getShebang());

        self::assertSame(
            ['The "shebang" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_cannot_register_an_invalid_shebang(): void
    {
        try {
            $this->setConfig([
                'shebang' => '/bin/php',
                'files' => [self::DEFAULT_FILE],
            ]);

            self::fail('Expected exception ot be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'The shebang line must start with "#!". Got "/bin/php" instead',
                $exception->getMessage(),
            );
        }

        try {
            $this->setConfig([
                'shebang' => true,
                'files' => [self::DEFAULT_FILE],
            ]);

            self::fail('Expected exception ot be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
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

            self::fail('Expected exception ot be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'The shebang should not be empty.',
                $exception->getMessage(),
            );
        }

        try {
            $this->setConfig([
                'shebang' => ' ',
                'files' => [self::DEFAULT_FILE],
            ]);

            self::fail('Expected exception ot be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
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

        self::assertSame($expected, $actual);

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_there_is_a_banner_registered_by_default(): void
    {
        $version = self::$version;

        $expected = <<<BANNER
            Generated by Humbug Box {$version}.

            @link https://github.com/humbug/box
            BANNER;

        self::assertSame($expected, $this->config->getStubBannerContents());
        self::assertNull($this->config->getStubBannerPath());

        $this->setConfig([
            'banner' => null,
            'files' => [self::DEFAULT_FILE],
        ]);

        $expected = <<<BANNER
            Generated by Humbug Box {$version}.

            @link https://github.com/humbug/box
            BANNER;

        self::assertSame($expected, $this->config->getStubBannerContents());
        self::assertNull($this->config->getStubBannerPath());

        self::assertSame(
            ['The "banner" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_banner_is_the_default_value(): void
    {
        $this->setConfig([
            'banner' => null,
        ]);

        self::assertSame(
            ['The "banner" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());

        $version = self::$version;

        $this->setConfig([
            'banner' => <<<BANNER
                Generated by Humbug Box {$version}.

                @link https://github.com/humbug/box
                BANNER,
        ]);

        self::assertSame(
            ['The "banner" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    #[DataProvider('customBannerProvider')]
    public function test_a_custom_banner_can_be_registered(string $banner): void
    {
        $this->setConfig([
            'banner' => $banner,
            'files' => [self::DEFAULT_FILE],
        ]);

        self::assertSame($banner, $this->config->getStubBannerContents());
        self::assertNull($this->config->getStubBannerPath());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_warning_is_given_when_the_banner_is_configured_but_the_box_stub_is_not_used(): void
    {
        FS::touch('my-stub.php');

        foreach (['my-stub.php', false] as $stub) {
            $this->setConfig([
                'banner' => 'custom banner',
                'stub' => $stub,
            ]);

            self::assertSame('custom banner', $this->config->getStubBannerContents());
            self::assertNull($this->config->getStubBannerPath());

            self::assertSame([], $this->config->getRecommendations());
            self::assertSame(
                ['The "banner" setting has been set but is ignored since the Box built-in stub is not being used'],
                $this->config->getWarnings(),
            );
        }
    }

    public function test_a_recommendation_is_given_when_the_banner_is_disabled_but_the_box_stub_is_not_used(): void
    {
        FS::touch('my-stub.php');

        foreach (['my-stub.php', false] as $stub) {
            $this->setConfig([
                'banner' => false,
                'stub' => $stub,
            ]);

            self::assertNull($this->config->getStubBannerContents());
            self::assertNull($this->config->getStubBannerPath());

            self::assertSame(
                ['The "banner" setting has been set but is unnecessary since the Box built-in stub is not being used'],
                $this->config->getRecommendations(),
            );
            self::assertSame([], $this->config->getWarnings());
        }
    }

    public function test_the_banner_can_be_disabled(): void
    {
        $this->setConfig([
            'banner' => false,
            'files' => [self::DEFAULT_FILE],
        ]);

        self::assertNull($this->config->getStubBannerContents());
        self::assertNull($this->config->getStubBannerPath());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_banner_must_be_a_valid_value(): void
    {
        try {
            $this->setConfig([
                'banner' => true,
                'files' => [self::DEFAULT_FILE],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'The banner cannot accept true as a value',
                $exception->getMessage(),
            );
        }
    }

    #[DataProvider('unormalizedCustomBannerProvider')]
    public function test_the_content_of_the_banner_is_normalized(string $banner, string $expected): void
    {
        $this->setConfig([
            'banner' => $banner,
            'files' => [self::DEFAULT_FILE],
        ]);

        self::assertSame($expected, $this->config->getStubBannerContents());
        self::assertNull($this->config->getStubBannerPath());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
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

        self::assertSame($comment, $this->config->getStubBannerContents());
        self::assertNull($this->config->getStubBannerPath());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_not_banner_path_is_registered_by_default(): void
    {
        self::assertNull($this->getNoFileConfig()->getStubBannerPath());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'banner-file' => null,
        ]);

        self::assertNull($this->config->getStubBannerPath());

        self::assertSame(
            ['The "banner-file" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_custom_banner_from_a_file_can_be_registered(): void
    {
        $comment = <<<'COMMENT'
            This is a

            multiline

            comment.
            COMMENT;

        FS::dumpFile('banner', $comment);

        $this->setConfig([
            'banner-file' => 'banner',
            'files' => [self::DEFAULT_FILE],
        ]);

        self::assertSame($comment, $this->config->getStubBannerContents());
        self::assertSame($this->tmp.DIRECTORY_SEPARATOR.'banner', $this->config->getStubBannerPath());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_default_stub_banner_path_is_configured(): void
    {
        $version = self::$version;

        $defaultBanner = <<<BANNER
            Generated by Humbug Box {$version}.

            @link https://github.com/humbug/box
            BANNER;

        $this->setConfig([
            'banner-file' => null,
        ]);

        self::assertSame($defaultBanner, $this->config->getStubBannerContents());
        self::assertNull($this->config->getStubBannerPath());

        self::assertSame(
            ['The "banner-file" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());

        FS::dumpFile('custom-banner', $defaultBanner);

        $this->setConfig([
            'banner-file' => 'custom-banner',
        ]);

        self::assertSame($defaultBanner, $this->config->getStubBannerContents());
        self::assertSame($this->tmp.DIRECTORY_SEPARATOR.'custom-banner', $this->config->getStubBannerPath());

        self::assertSame(
            ['The "banner-file" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());

        $version = self::$version;

        FS::dumpFile(
            'custom-banner',
            <<<BANNER
                  Generated by Humbug Box {$version}.

                  @link https://github.com/humbug/box
                BANNER,
        );

        $this->setConfig([
            'banner-file' => 'custom-banner',
        ]);

        self::assertSame($defaultBanner, $this->config->getStubBannerContents());
        self::assertSame($this->tmp.DIRECTORY_SEPARATOR.'custom-banner', $this->config->getStubBannerPath());

        self::assertSame(
            ['The "banner-file" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_warning_is_given_when_the_banner_file_is_configured_but_the_box_stub_is_not_used(): void
    {
        FS::touch('custom-banner');
        FS::touch('my-stub.php');

        foreach (['my-stub.php', false] as $stub) {
            $this->setConfig([
                'banner-file' => 'custom-banner',
                'stub' => $stub,
            ]);

            self::assertSame('', $this->config->getStubBannerContents());
            self::assertSame($this->tmp.DIRECTORY_SEPARATOR.'custom-banner', $this->config->getStubBannerPath());

            self::assertSame([], $this->config->getRecommendations());
            self::assertSame(
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

        FS::dumpFile('banner', $comment);

        $this->setConfig([
            'banner' => 'discarded banner',
            'banner-file' => 'banner',
            'files' => [self::DEFAULT_FILE],
        ]);

        self::assertSame($comment, $this->config->getStubBannerContents());
        self::assertSame($this->tmp.DIRECTORY_SEPARATOR.'banner', $this->config->getStubBannerPath());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
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

        FS::dumpFile('banner', $comment);

        $this->setConfig([
            'banner-file' => 'banner',
            'files' => [self::DEFAULT_FILE],
        ]);

        self::assertSame($expected, $this->config->getStubBannerContents());
        self::assertSame($this->tmp.DIRECTORY_SEPARATOR.'banner', $this->config->getStubBannerPath());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_custom_banner_file_must_exists_when_provided(): void
    {
        try {
            $this->setConfig([
                'banner-file' => '/does/not/exist',
                'files' => [self::DEFAULT_FILE],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'The file "/does/not/exist" does not exist.',
                $exception->getMessage(),
            );
        }
    }

    public function test_by_default_there_is_no_stub_and_the_stub_is_generated(): void
    {
        self::assertNull($this->config->getStubPath());
        self::assertTrue($this->config->isStubGenerated());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'stub' => null,
        ]);

        self::assertNull($this->config->getStubPath());
        self::assertTrue($this->config->isStubGenerated());

        self::assertSame(
            ['The "stub" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'stub' => true,
        ]);

        self::assertNull($this->config->getStubPath());
        self::assertTrue($this->config->isStubGenerated());

        self::assertSame(
            ['The "stub" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_custom_stub_can_be_provided(): void
    {
        FS::dumpFile('custom-stub.php');

        $this->setConfig([
            'stub' => 'custom-stub.php',
            'files' => [self::DEFAULT_FILE],
        ]);

        self::assertSame($this->tmp.DIRECTORY_SEPARATOR.'custom-stub.php', $this->config->getStubPath());
        self::assertFalse($this->config->isStubGenerated());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_stub_can_be_generated(): void
    {
        $this->setConfig([
            'files' => [self::DEFAULT_FILE],
        ]);

        self::assertNull($this->config->getStubPath());
        self::assertTrue($this->config->isStubGenerated());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());

        foreach ([true, null] as $stub) {
            $this->setConfig([
                'stub' => $stub,
                'files' => [self::DEFAULT_FILE],
            ]);

            self::assertNull($this->config->getStubPath());
            self::assertTrue($this->config->isStubGenerated());
        }
    }

    public function test_the_default_stub_can_be_used(): void
    {
        $this->setConfig([
            'stub' => false,
            'files' => [self::DEFAULT_FILE],
        ]);

        self::assertNull($this->config->getStubPath());
        self::assertFalse($this->config->isStubGenerated());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_funcs_are_not_intercepted_by_default(): void
    {
        self::assertFalse($this->config->isInterceptFileFuncs());

        $this->setConfig([
            'intercept' => null,
        ]);

        self::assertFalse($this->config->isInterceptFileFuncs());

        self::assertSame(
            ['The "intercept" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'intercept' => false,
        ]);

        self::assertFalse($this->config->isInterceptFileFuncs());

        self::assertSame(
            ['The "intercept" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_intercept_funcs_can_be_enabled(): void
    {
        $this->setConfig([
            'intercept' => true,
        ]);

        self::assertTrue($this->config->isInterceptFileFuncs());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_warning_is_given_when_the_intercept_funcs_is_configured_but_the_box_stub_is_not_used(): void
    {
        FS::touch('my-stub.php');

        foreach (['my-stub.php', false] as $stub) {
            $this->setConfig([
                'intercept' => true,
                'stub' => $stub,
            ]);

            self::assertTrue($this->config->isInterceptFileFuncs());

            self::assertSame([], $this->config->getRecommendations());
            self::assertSame(
                ['The "intercept" setting has been set but is ignored since the Box built-in stub is not being used'],
                $this->config->getWarnings(),
            );
        }
    }

    public function test_a_recommendation_is_given_when_the_intercept_funcs_is_disabled_but_the_box_stub_is_not_used(): void
    {
        FS::touch('my-stub.php');

        foreach (['my-stub.php', false] as $stub) {
            $this->setConfig([
                'intercept' => false,
                'stub' => $stub,
            ]);

            self::assertFalse($this->config->isInterceptFileFuncs());

            self::assertSame(
                ['The "intercept" setting can be omitted since is set to its default value'],
                $this->config->getRecommendations(),
            );
            self::assertSame([], $this->config->getWarnings());
        }
    }

    public function test_the_requirement_checker_can_be_disabled(): void
    {
        $this->setConfig([
            'check-requirements' => false,
        ]);

        self::assertFalse($this->config->checkRequirements());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());

        FS::dumpFile('composer.lock', '{}');

        $this->reloadConfig();

        self::assertFalse($this->config->checkRequirements());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_requirement_checker_is_enabled_by_default_if_a_composer_json_is_found(): void
    {
        // Sanity check
        self::assertFalse($this->config->checkRequirements());

        FS::dumpFile('composer.json', '{}');

        $this->reloadConfig();

        self::assertTrue($this->config->checkRequirements());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_requirement_checker_is_enabled_by_default_if_a_composer_lock_is_found(): void
    {
        // Sanity check
        self::assertFalse($this->config->checkRequirements());

        FS::dumpFile('composer.lock', '{}');

        $this->reloadConfig();

        self::assertTrue($this->config->checkRequirements());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_requirement_checker_is_enabled_by_default_if_a_composer_json_and_lock_file_is_found(): void
    {
        // Sanity check
        self::assertFalse($this->config->checkRequirements());

        FS::dumpFile('composer.json', '{}');
        FS::dumpFile('composer.lock', '{}');

        $this->reloadConfig();

        self::assertTrue($this->config->checkRequirements());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_requirement_checker_can_be_enabled(): void
    {
        FS::dumpFile('composer.json', '{}');
        FS::dumpFile('composer.lock', '{}');

        $this->setConfig([
            'check-requirements' => true,
        ]);

        self::assertTrue($this->config->checkRequirements());

        self::assertSame(
            ['The "check-requirements" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_requirement_checker_is_forcibly_disabled_if_the_composer_files_could_not_be_found(): void
    {
        $this->setConfig([
            'check-requirements' => true,
        ]);

        self::assertFalse($this->config->checkRequirements());

        self::assertSame(
            ['The "check-requirements" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame(
            ['The requirement checker could not be used because the composer.json and composer.lock file could not be found.'],
            $this->config->getWarnings(),
        );
    }

    public function test_the_requirement_checker_is_not_disabled_but_a_warning_is_emitted_if_enabled_without_a_composer_lock_file(): void
    {
        FS::dumpFile('composer.json', '{}');

        $this->setConfig([
            'check-requirements' => true,
        ]);

        self::assertTrue($this->config->checkRequirements());

        self::assertSame(
            ['The "check-requirements" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame(
            ['Enabling the requirement checker when there is no composer.lock is deprecated. In the future the requirement checker will be forcefully skipped in this scenario.'],
            $this->config->getWarnings(),
        );
    }

    public function test_a_recommendation_is_given_if_the_default_check_requirement_value_is_given(): void
    {
        $this->setConfig([
            'check-requirements' => null,
        ]);

        self::assertFalse($this->config->checkRequirements());

        self::assertSame(
            ['The "check-requirements" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame(
            ['The requirement checker could not be used because the composer.json and composer.lock file could not be found.'],
            $this->config->getWarnings(),
        );

        $this->setConfig([
            'check-requirements' => true,
        ]);

        self::assertFalse($this->config->checkRequirements());

        self::assertSame(
            ['The "check-requirements" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame(
            ['The requirement checker could not be used because the composer.json and composer.lock file could not be found.'],
            $this->config->getWarnings(),
        );

        FS::dumpFile('composer.json', '{}');
        FS::dumpFile('composer.lock', '{}');
        FS::dumpFile('vendor/composer/installed.json', '{}');

        $this->setConfig([
            'check-requirements' => null,
        ]);

        self::assertTrue($this->config->checkRequirements());

        self::assertSame(
            ['The "check-requirements" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'check-requirements' => true,
        ]);

        self::assertTrue($this->config->checkRequirements());

        self::assertSame(
            ['The "check-requirements" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_warning_is_given_if_the_check_requirement_is_configured_but_the_phar_stub_used(): void
    {
        FS::dumpFile('composer.json', '{}');
        FS::dumpFile('composer.lock', '{}');
        FS::dumpFile('vendor/composer/installed.json', '{}');

        $this->setConfig([
            'check-requirements' => true,
            'stub' => false,
        ]);

        self::assertTrue($this->config->checkRequirements());

        self::assertSame(
            ['The "check-requirements" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame(
            ['The "check-requirements" setting has been set but has been ignored since the PHAR built-in stub is being used.'],
            $this->config->getWarnings(),
        );
    }

    public function test_by_default_dev_files_are_excluded_if_dump_autoload_is_enabled(): void
    {
        // Those checks are somewhat redundant. They are however done here to make sure the exclude dev files
        // checks stay in sync with the dump-autoload
        self::assertFalse($this->config->dumpAutoload());
        self::assertFalse($this->getNoFileConfig()->dumpAutoload());

        self::assertFalse($this->config->excludeDevFiles());
        self::assertFalse($this->getNoFileConfig()->excludeDevFiles());

        $this->setConfig(['exclude-dev-files' => null]);

        self::assertFalse($this->config->excludeDevFiles());

        self::assertSame(
            ['The "exclude-dev-files" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());

        FS::dumpFile('composer.json', '{}');

        $this->setConfig([]);

        self::assertTrue($this->config->dumpAutoload());
        self::assertTrue($this->getNoFileConfig()->dumpAutoload());

        self::assertTrue($this->config->excludeDevFiles());
        self::assertTrue($this->getNoFileConfig()->excludeDevFiles());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());

        $this->setConfig(['exclude-dev-files' => null]);

        self::assertTrue($this->config->dumpAutoload());
        self::assertTrue($this->config->excludeDevFiles());

        self::assertSame(
            ['The "exclude-dev-files" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'dump-autoload' => false,
        ]);

        self::assertFalse($this->config->dumpAutoload());
        self::assertTrue($this->getNoFileConfig()->dumpAutoload());

        self::assertFalse($this->config->excludeDevFiles());
        self::assertTrue($this->getNoFileConfig()->excludeDevFiles());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());

        $this->setConfig([
            'dump-autoload' => true,
        ]);

        self::assertTrue($this->config->dumpAutoload());
        self::assertTrue($this->getNoFileConfig()->dumpAutoload());

        self::assertTrue($this->config->excludeDevFiles());
        self::assertTrue($this->getNoFileConfig()->excludeDevFiles());

        self::assertSame(
            ['The "dump-autoload" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_exclude_dev_files_is_enabled_when_the_autoload_is_dumped(): void
    {
        FS::dumpFile('composer.json', '{}');

        $this->setConfig([
            'dump-autoload' => true,
            'exclude-dev-files' => true,
        ]);

        self::assertTrue($this->config->dumpAutoload());
        self::assertTrue($this->getNoFileConfig()->dumpAutoload());

        self::assertTrue($this->config->excludeDevFiles());
        self::assertTrue($this->getNoFileConfig()->excludeDevFiles());

        self::assertSame(
            [
                'The "dump-autoload" setting can be omitted since is set to its default value',
                'The "exclude-dev-files" setting can be omitted since is set to its default value',
            ],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_exclude_dev_files_is_disabled_when_the_autoload_is_not_dumped(): void
    {
        FS::dumpFile('composer.json', '{}');

        $this->setConfig([
            'dump-autoload' => false,
            'exclude-dev-files' => false,
        ]);

        self::assertFalse($this->config->dumpAutoload());
        self::assertTrue($this->getNoFileConfig()->dumpAutoload());

        self::assertFalse($this->config->excludeDevFiles());
        self::assertTrue($this->getNoFileConfig()->excludeDevFiles());

        self::assertSame(
            ['The "exclude-dev-files" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_the_dev_files_can_be_not_excluded_when_the_autoloader_is_dumped(): void
    {
        FS::dumpFile('composer.json', '{}');

        $this->setConfig([
            'dump-autoload' => true,
            'exclude-dev-files' => false,
        ]);

        self::assertTrue($this->config->dumpAutoload());
        self::assertTrue($this->getNoFileConfig()->dumpAutoload());

        self::assertFalse($this->config->excludeDevFiles());
        self::assertTrue($this->getNoFileConfig()->excludeDevFiles());

        self::assertSame(
            ['The "dump-autoload" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_warning_is_given_if_dev_files_are_explicitly_excluded_but_the_autoloader_not_dumped(): void
    {
        FS::dumpFile('composer.json', '{}');

        $this->setConfig([
            'dump-autoload' => false,
            'exclude-dev-files' => true,
        ]);

        self::assertFalse($this->config->dumpAutoload());
        self::assertTrue($this->getNoFileConfig()->dumpAutoload());

        self::assertFalse($this->config->excludeDevFiles());
        self::assertTrue($this->getNoFileConfig()->excludeDevFiles());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame(
            ['The "exclude-dev-files" setting has been set but has been ignored because the Composer autoloader is not dumped'],
            $this->config->getWarnings(),
        );
    }

    public function test_the_timestamp_can_be_configured(): void
    {
        $this->setConfig([
            'timestamp' => '2020-10-20T10:01:11+00:00',
        ]);

        self::assertSame(
            '2020-10-20T10:01:11+00:00',
            $this->config->getTimestamp()?->format(DateTimeInterface::ATOM),
        );

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_recommendation_is_given_if_the_timestamp_configured_is_the_default_value(): void
    {
        $this->setConfig([
            'timestamp' => null,
        ]);

        self::assertNull($this->config->getTimestamp());

        self::assertSame(
            ['The "timestamp" setting can be omitted since is set to its default value'],
            $this->config->getRecommendations(),
        );
        self::assertSame([], $this->config->getWarnings());
    }

    public function test_a_warning_is_given_if_the_timestamp_is_configured_with_an_openssl_signature(): void
    {
        FS::touch('private-key');

        $this->setConfig([
            'timestamp' => '2020-10-20T10:01:11+00:00',
            'algorithm' => 'OPENSSL',
            'key' => 'private-key',
        ]);

        self::assertNull($this->config->getTimestamp());

        self::assertSame([], $this->config->getRecommendations());
        self::assertSame(
            [
                'Using an OpenSSL signature is deprecated and will be removed in 5.0.0. Please check https://github.com/box-project/box/blob/main/doc/phar-signing.md for alternatives.',
                'The "timestamp" setting has been set but has been ignored since an OpenSSL signature has been configured (setting "algorithm").',
            ],
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

        self::assertFalse($this->config->checkRequirements());
        self::assertFalse($this->config->dumpAutoload());
        self::assertTrue($this->config->excludeComposerArtifacts());
        self::assertMatchesRegularExpression('/^box-auto-generated-alias-[\da-zA-Z]{12}\.phar$/', $this->config->getAlias());
        self::assertSame($this->tmp, $this->config->getBasePath());
        self::assertSame([], $this->config->getBinaryFiles());
        self::assertSame([], $this->config->getCompactors()->toArray());
        self::assertFalse($this->config->hasAutodiscoveredFiles());
        self::assertNull($this->config->getComposerJson());
        self::assertNull($this->config->getComposerLock());
        self::assertSame(CompressionAlgorithm::NONE, $this->config->getCompressionAlgorithm());
        self::assertSame($this->tmp.'/box.json', $this->config->getConfigurationFile());
        self::assertEquals(
            new MapFile($this->tmp, []),
            $this->config->getFileMapper(),
        );
        self::assertSame(493, $this->config->getFileMode());
        self::assertSame([], $this->config->getFiles());
        self::assertSame('', $this->config->getMainScriptContents());
        self::assertSame($this->tmp.'/index.php', $this->config->getMainScriptPath());
        self::assertNull($this->config->getMetadata());
        self::assertSame($this->tmp.'/index.phar', $this->config->getOutputPath());
        self::assertNull($this->config->getPrivateKeyPassphrase());
        self::assertNull($this->config->getPrivateKeyPath());
        self::assertSame([], $this->config->getReplacements());
        self::assertSame('#!/usr/bin/env php', $this->config->getShebang());
        self::assertSame(SigningAlgorithm::SHA512, $this->config->getSigningAlgorithm());

        $version = self::$version;

        self::assertSame(
            <<<BANNER
                Generated by Humbug Box {$version}.

                @link https://github.com/humbug/box
                BANNER,
            $this->config->getStubBannerContents(),
        );
        self::assertNull($this->config->getStubPath());
        self::assertSame($this->tmp.'/index.phar', $this->config->getTmpOutputPath());
        self::assertTrue($this->config->hasMainScript());
        self::assertFalse($this->config->isInterceptFileFuncs());
        self::assertFalse($this->config->promptForPrivateKey());
        self::assertTrue($this->config->isStubGenerated());
        self::assertNull($this->config->getTimestamp());
    }

    public function test_it_can_be_exported(): void
    {
        FS::touch('foo.php');
        FS::touch('bar.php');

        FS::dumpFile(
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
        FS::dumpFile('composer.lock', '{}');
        FS::dumpFile('vendor/composer/installed.json', '{}');

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

        $expectedDumpedConfig = <<<'EOF'
            KevinGH\Box\Configuration\ExportableConfiguration {#100
              -file: "box.json"
              -alias: "test.phar"
              -basePath: "/path/to"
              -composerJson: KevinGH\Box\Composer\Artifact\ComposerArtifact {#100
                +path: "composer.json"
                +decodedContents: array:1 [
                  "config" => array:3 [
                    "bin-dir" => "bin"
                    "platform" => array:1 [
                      "php" => "7.1.10"
                    ]
                    "sort-packages" => true
                  ]
                ]
              }
              -composerLock: KevinGH\Box\Composer\Artifact\ComposerArtifact {#100
                +path: "composer.lock"
                +decodedContents: []
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
              -excludeComposerArtifacts: true
              -excludeDevFiles: true
              -compactors: array:1 [
                0 => "KevinGH\Box\Compactor\Php"
              ]
              -compressionAlgorithm: "GZ"
              -fileMode: "0755"
              -mainScriptPath: "index.php"
              -mainScriptContents: ""
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
              -signingAlgorithm: "SHA512"
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

        self::assertSame($expectedDumpedConfig, $actualDumpedConfig);
    }

    public static function invalidCompressionAlgorithmsProvider(): iterable
    {
        yield 'Invalid string key' => [
            'INVALID',
            'Unknown compression algorithm "INVALID". Expected one of "GZ", "BZ2", "NONE".',
        ];

        yield 'Invalid constant value' => [
            10,
            'Unknown compression algorithm "10". Expected one of "GZ", "BZ2", "NONE".',
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
                COMMENT,
            <<<'COMMENT'
                This is a

                multiline

                banner.
                COMMENT,
        ];
    }

    public static function composerArtifactsProvider(): iterable
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
                FS::dumpFile('composer.json', '{}');
            },
            'composer.json',
            [],
            null,
            null,
        ];

        yield [
            static function (): void {
                FS::dumpFile('composer.json', '{"name": "acme/foo"}');
            },
            'composer.json',
            ['name' => 'acme/foo'],
            null,
            null,
        ];

        yield [
            static function (): void {
                FS::dumpFile('composer.lock', '{}');
            },
            null,
            null,
            'composer.lock',
            [],
        ];

        yield [
            static function (): void {
                FS::dumpFile('composer.lock', '{"name": "acme/foo"}');
            },
            null,
            null,
            'composer.lock',
            ['name' => 'acme/foo'],
        ];

        yield [
            static function (): void {
                FS::dumpFile('composer.json', '{"name": "acme/foo"}');
                FS::dumpFile('composer.lock', '{"name": "acme/bar"}');
            },
            'composer.json',
            ['name' => 'acme/foo'],
            'composer.lock',
            ['name' => 'acme/bar'],
        ];

        yield [
            static function (): void {
                FS::mkdir('composer.json');
            },
            null,
            null,
            null,
            null,
        ];

        yield [
            static function (): void {
                FS::mkdir('composer.lock');
            },
            null,
            null,
            null,
            null,
        ];

        yield [
            static function (): void {
                FS::touch('composer.json');
                FS::chmod('composer.json', 0);
            },
            null,
            null,
            null,
            null,
        ];

        yield [
            static function (): void {
                FS::touch('composer.lock');
                FS::chmod('composer.lock', 0);
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
            FS::getFileContents(__DIR__.'/../../res/schema.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return array_keys($schema['properties']);
    }
}
