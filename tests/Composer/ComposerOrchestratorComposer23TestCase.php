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

namespace KevinGH\Box\Composer;

use Fidry\Console\DisplayNormalizer;
use Fidry\FileSystem\FS;
use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use function file_get_contents;
use function preg_replace;

/**
 * @internal
 */
#[CoversClass(AutoloadDumper::class)]
#[CoversClass(ComposerOrchestrator::class)]
#[CoversClass(ComposerProcessFactory::class)]
class ComposerOrchestratorComposer23TestCase extends BaseComposerOrchestratorComposerTestCase
{
    protected function shouldSkip(string $composerVersion): array
    {
        return [
            version_compare($composerVersion, '2.3.0', '<')
                || version_compare($composerVersion, '2.4.0', '>='),
            '~2.3.0',
        ];
    }

    #[DataProvider('composerAutoloadProvider')]
    public function test_it_can_dump_the_autoloader_with_an_empty_composer_json(
        SymbolsRegistry $symbolsRegistry,
        string $prefix,
        string $expectedAutoloadContents,
    ): void {
        FS::dumpFile('composer.json', '{}');

        $this->composerOrchestrator->dumpAutoload($symbolsRegistry, $prefix, false, []);

        $expectedPaths = [
            'composer.json',
            'vendor/autoload.php',
            'vendor/composer/autoload_classmap.php',
            'vendor/composer/autoload_namespaces.php',
            'vendor/composer/autoload_psr4.php',
            'vendor/composer/autoload_real.php',
            'vendor/composer/autoload_static.php',
            'vendor/composer/ClassLoader.php',
            'vendor/composer/LICENSE',
        ];

        $actualPaths = $this->retrievePaths();

        self::assertSame($expectedPaths, $actualPaths);

        $actualAutoloadContents = preg_replace(
            '/ComposerAutoloaderInit[a-z\d]{32}/',
            'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05',
            file_get_contents($this->tmp.'/vendor/autoload.php'),
        );
        $actualAutoloadContents = DisplayNormalizer::removeTrailingSpaces($actualAutoloadContents);

        self::assertSame($expectedAutoloadContents, $actualAutoloadContents);

        self::assertSame(
            <<<'PHP'
                <?php

                // autoload_psr4.php @generated by Composer

                $vendorDir = dirname(__DIR__);
                $baseDir = dirname($vendorDir);

                return array(
                );

                PHP,
            preg_replace(
                '/ComposerAutoloaderInit[a-z\d]{32}/',
                'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05',
                file_get_contents($this->tmp.'/vendor/composer/autoload_psr4.php'),
            ),
        );
    }

    #[DataProvider('composerAutoloadProvider')]
    public function test_it_cannot_dump_the_autoloader_with_an_invalid_composer_json(
        SymbolsRegistry $symbolsRegistry,
        string $prefix,
    ): void {
        FS::mirror(self::FIXTURES.'/dir000', $this->tmp);

        FS::dumpFile('composer.json');

        try {
            $this->composerOrchestrator->dumpAutoload($symbolsRegistry, $prefix, false, []);

            self::fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Could not dump the autoloader.',
                $exception->getMessage(),
            );
            self::assertSame(0, $exception->getCode());
            self::assertNotNull($exception->getPrevious());

            self::assertStringContainsString(
                '"./composer.json" does not contain valid JSON',
                $exception->getPrevious()->getMessage(),
            );
        }
    }

    public function test_it_can_dump_the_autoloader_with_a_composer_json_with_a_dependency(): void
    {
        FS::mirror(self::FIXTURES.'/dir000', $this->tmp);

        $this->composerOrchestrator->dumpAutoload(new SymbolsRegistry(), '', false, []);

        $expectedPaths = [
            'composer.json',
            'vendor/autoload.php',
            'vendor/composer/autoload_classmap.php',
            'vendor/composer/autoload_namespaces.php',
            'vendor/composer/autoload_psr4.php',
            'vendor/composer/autoload_real.php',
            'vendor/composer/autoload_static.php',
            'vendor/composer/ClassLoader.php',
            'vendor/composer/LICENSE',
        ];

        $actualPaths = $this->retrievePaths();

        self::assertSame($expectedPaths, $actualPaths);

        self::assertSame(
            <<<'PHP'
                <?php

                // autoload.php @generated by Composer

                if (PHP_VERSION_ID < 50600) {
                    echo 'Composer 2.3.0 dropped support for autoloading on PHP <5.6 and you are running '.PHP_VERSION.', please upgrade PHP or use Composer 2.2 LTS via "composer self-update --2.2". Aborting.'.PHP_EOL;
                    exit(1);
                }

                require_once __DIR__ . '/composer/autoload_real.php';

                return ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05::getLoader();

                PHP,
            preg_replace(
                '/ComposerAutoloaderInit[a-z\d]{32}/',
                'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05',
                file_get_contents($this->tmp.'/vendor/autoload.php'),
            ),
        );

        self::assertSame(
            <<<'PHP'
                <?php

                // autoload_psr4.php @generated by Composer

                $vendorDir = dirname(__DIR__);
                $baseDir = dirname($vendorDir);

                return array(
                );

                PHP,
            preg_replace(
                '/ComposerAutoloaderInit[a-z\d]{32}/',
                'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05',
                file_get_contents($this->tmp.'/vendor/composer/autoload_psr4.php'),
            ),
        );
    }

    #[DataProvider('composerAutoloadProvider')]
    public function test_it_cannot_dump_the_autoloader_if_the_composer_json_file_is_missing(
        SymbolsRegistry $symbolsRegistry,
        string $prefix,
    ): void {
        try {
            $this->composerOrchestrator->dumpAutoload($symbolsRegistry, $prefix, false, []);

            self::fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Could not dump the autoloader.',
                $exception->getMessage(),
            );
            self::assertSame(0, $exception->getCode());
            self::assertNotNull($exception->getPrevious());

            self::assertStringContainsString(
                'Composer could not find a composer.json file in',
                $exception->getPrevious()->getMessage(),
            );
        }
    }

    #[DataProvider('composerAutoloadProvider')]
    public function test_it_can_dump_the_autoloader_with_a_composer_json_lock_and_installed_with_a_dependency(
        SymbolsRegistry $SymbolsRegistry,
        string $prefix,
        string $expectedAutoloadContents,
    ): void {
        $this->skipIfFixturesNotInstalled(self::FIXTURES.'/dir001/vendor');
        FS::mirror(self::FIXTURES.'/dir001', $this->tmp);

        $this->composerOrchestrator->dumpAutoload($SymbolsRegistry, $prefix, false);

        // The fact that there is a dependency in the `composer.json` does not change anything to Composer
        $expectedPaths = [
            'composer.json',
            'composer.lock',
            'vendor/autoload.php',
            'vendor/beberlei/assert/composer.json',
            'vendor/beberlei/assert/lib/Assert/Assert.php',
            'vendor/beberlei/assert/lib/Assert/Assertion.php',
            'vendor/beberlei/assert/lib/Assert/AssertionChain.php',
            'vendor/beberlei/assert/lib/Assert/AssertionFailedException.php',
            'vendor/beberlei/assert/lib/Assert/functions.php',
            'vendor/beberlei/assert/lib/Assert/InvalidArgumentException.php',
            'vendor/beberlei/assert/lib/Assert/LazyAssertion.php',
            'vendor/beberlei/assert/lib/Assert/LazyAssertionException.php',
            'vendor/beberlei/assert/LICENSE',
            'vendor/composer/autoload_classmap.php',
            'vendor/composer/autoload_files.php',
            'vendor/composer/autoload_namespaces.php',
            'vendor/composer/autoload_psr4.php',
            'vendor/composer/autoload_real.php',
            'vendor/composer/autoload_static.php',
            'vendor/composer/ClassLoader.php',
            'vendor/composer/installed.json',
            'vendor/composer/installed.php',
            'vendor/composer/InstalledVersions.php',
            'vendor/composer/LICENSE',
            'vendor/composer/platform_check.php',
        ];

        $actualPaths = $this->retrievePaths();

        self::assertSame($expectedPaths, $actualPaths);

        self::assertSame(
            $expectedAutoloadContents,
            preg_replace(
                '/ComposerAutoloaderInit[a-z\d]{32}/',
                'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05',
                file_get_contents($this->tmp.'/vendor/autoload.php'),
            ),
        );

        self::assertSame(
            <<<'PHP'
                <?php

                // autoload_psr4.php @generated by Composer

                $vendorDir = dirname(__DIR__);
                $baseDir = dirname($vendorDir);

                return array(
                    'Assert\\' => array($vendorDir . '/beberlei/assert/lib/Assert'),
                );

                PHP,
            preg_replace(
                '/ComposerAutoloaderInit[a-z\d]{32}/',
                'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05',
                file_get_contents($this->tmp.'/vendor/composer/autoload_psr4.php'),
            ),
        );
    }

    public function test_it_can_dump_the_autoloader_with_a_composer_json_lock_and_installed_with_a_dev_dependency(): void
    {
        $this->skipIfFixturesNotInstalled(self::FIXTURES.'/dir003/vendor');
        FS::mirror(self::FIXTURES.'/dir003', $this->tmp);

        $composerAutoloaderName = self::COMPOSER_AUTOLOADER_NAME;

        $expectedAutoloadContents = <<<PHP
            <?php

            // autoload.php @generated by Composer

            if (PHP_VERSION_ID < 50600) {
                echo 'Composer 2.3.0 dropped support for autoloading on PHP <5.6 and you are running '.PHP_VERSION.', please upgrade PHP or use Composer 2.2 LTS via "composer self-update --2.2". Aborting.'.PHP_EOL;
                exit(1);
            }

            require_once __DIR__ . '/composer/autoload_real.php';

            return {$composerAutoloaderName}::getLoader();

            PHP;

        $this->composerOrchestrator->dumpAutoload(
            new SymbolsRegistry(),
            '',
            true,
            [],
        );

        // The fact that there is a dependency in the `composer.json` does not change anything to Composer
        $expectedPaths = [
            'composer.json',
            'composer.lock',
            'vendor/autoload.php',
            'vendor/beberlei/assert/composer.json',
            'vendor/beberlei/assert/lib/Assert/Assert.php',
            'vendor/beberlei/assert/lib/Assert/Assertion.php',
            'vendor/beberlei/assert/lib/Assert/AssertionChain.php',
            'vendor/beberlei/assert/lib/Assert/AssertionFailedException.php',
            'vendor/beberlei/assert/lib/Assert/functions.php',
            'vendor/beberlei/assert/lib/Assert/InvalidArgumentException.php',
            'vendor/beberlei/assert/lib/Assert/LazyAssertion.php',
            'vendor/beberlei/assert/lib/Assert/LazyAssertionException.php',
            'vendor/beberlei/assert/LICENSE',
            'vendor/composer/autoload_classmap.php',
            'vendor/composer/autoload_namespaces.php',
            'vendor/composer/autoload_psr4.php',
            'vendor/composer/autoload_real.php',
            'vendor/composer/autoload_static.php',
            'vendor/composer/ClassLoader.php',
            'vendor/composer/installed.json',
            'vendor/composer/installed.php',
            'vendor/composer/InstalledVersions.php',
            'vendor/composer/LICENSE',
        ];

        $actualPaths = $this->retrievePaths();

        self::assertSame($expectedPaths, $actualPaths);

        self::assertSame(
            $expectedAutoloadContents,
            preg_replace(
                '/ComposerAutoloaderInit[a-z\d]{32}/',
                'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05',
                file_get_contents($this->tmp.'/vendor/autoload.php'),
            ),
        );

        self::assertSame(
            <<<'PHP'
                <?php

                // autoload_psr4.php @generated by Composer

                $vendorDir = dirname(__DIR__);
                $baseDir = dirname($vendorDir);

                return array(
                );

                PHP,
            preg_replace(
                '/ComposerAutoloaderInit[a-z\d]{32}/',
                'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05',
                file_get_contents($this->tmp.'/vendor/composer/autoload_psr4.php'),
            ),
        );
    }

    #[DataProvider('composerAutoloadProvider')]
    public function test_it_can_dump_the_autoloader_with_a_composer_json_and_lock_with_a_dependency(
        SymbolsRegistry $symbolsRegistry,
        string $prefix,
        string $expectedAutoloadContents,
    ): void {
        $this->skipIfFixturesNotInstalled(self::FIXTURES.'/dir002/vendor');
        FS::mirror(self::FIXTURES.'/dir002', $this->tmp);

        $this->composerOrchestrator->dumpAutoload($symbolsRegistry, $prefix, false, []);

        // The fact that there is a dependency in the `composer.json` does not change anything to Composer
        $expectedPaths = [
            'composer.json',
            'composer.lock',
            'vendor/autoload.php',
            'vendor/beberlei/assert/composer.json',
            'vendor/beberlei/assert/lib/Assert/Assert.php',
            'vendor/beberlei/assert/lib/Assert/Assertion.php',
            'vendor/beberlei/assert/lib/Assert/AssertionChain.php',
            'vendor/beberlei/assert/lib/Assert/AssertionFailedException.php',
            'vendor/beberlei/assert/lib/Assert/functions.php',
            'vendor/beberlei/assert/lib/Assert/InvalidArgumentException.php',
            'vendor/beberlei/assert/lib/Assert/LazyAssertion.php',
            'vendor/beberlei/assert/lib/Assert/LazyAssertionException.php',
            'vendor/beberlei/assert/LICENSE',
            'vendor/composer/autoload_classmap.php',
            'vendor/composer/autoload_files.php',
            'vendor/composer/autoload_namespaces.php',
            'vendor/composer/autoload_psr4.php',
            'vendor/composer/autoload_real.php',
            'vendor/composer/autoload_static.php',
            'vendor/composer/ClassLoader.php',
            'vendor/composer/installed.json',
            'vendor/composer/installed.php',
            'vendor/composer/InstalledVersions.php',
            'vendor/composer/LICENSE',
            'vendor/composer/platform_check.php',
        ];

        $actualPaths = $this->retrievePaths();

        self::assertEqualsCanonicalizing($expectedPaths, $actualPaths);

        self::assertSame(
            $expectedAutoloadContents,
            preg_replace(
                '/ComposerAutoloaderInit[a-z\d]{32}/',
                'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05',
                file_get_contents($this->tmp.'/vendor/autoload.php'),
            ),
        );

        self::assertSame(
            <<<'PHP'
                <?php

                // autoload_psr4.php @generated by Composer

                $vendorDir = dirname(__DIR__);
                $baseDir = dirname($vendorDir);

                return array(
                    'Assert\\' => array($vendorDir . '/beberlei/assert/lib/Assert'),
                );

                PHP,
            preg_replace(
                '/ComposerAutoloaderInit[a-z\d]{32}/',
                'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05',
                file_get_contents($this->tmp.'/vendor/composer/autoload_psr4.php'),
            ),
        );
    }

    public static function composerAutoloadProvider(): iterable
    {
        $composerAutoloaderName = self::COMPOSER_AUTOLOADER_NAME;

        yield 'Empty registry' => [
            new SymbolsRegistry(),
            '',
            <<<PHP
                <?php

                // autoload.php @generated by Composer

                if (PHP_VERSION_ID < 50600) {
                    echo 'Composer 2.3.0 dropped support for autoloading on PHP <5.6 and you are running '.PHP_VERSION.', please upgrade PHP or use Composer 2.2 LTS via "composer self-update --2.2". Aborting.'.PHP_EOL;
                    exit(1);
                }

                require_once __DIR__ . '/composer/autoload_real.php';

                return {$composerAutoloaderName}::getLoader();

                PHP,
        ];

        yield 'Registry with recorded class' => [
            self::createSymbolsRegistry(
                [['Acme\Foo', '_Box\Acme\Foo']],
            ),
            '_Box',
            <<<PHP
                <?php

                // @generated by Humbug Box

                \$loader = (static function () {
                    // Backup the autoloaded Composer files
                    \$existingComposerAutoloadFiles = \$GLOBALS['__composer_autoload_files'] ?? [];

                    // @generated by Humbug Box

                    // autoload.php @generated by Composer

                    if (PHP_VERSION_ID < 50600) {
                        echo 'Composer 2.3.0 dropped support for autoloading on PHP <5.6 and you are running '.PHP_VERSION.', please upgrade PHP or use Composer 2.2 LTS via "composer self-update --2.2". Aborting.'.PHP_EOL;
                        exit(1);
                    }

                    require_once __DIR__ . '/composer/autoload_real.php';

                    \$loader = {$composerAutoloaderName}::getLoader();

                    // Ensure InstalledVersions is available
                    \$installedVersionsPath = __DIR__.'/composer/InstalledVersions.php';
                    if (file_exists(\$installedVersionsPath)) require_once \$installedVersionsPath;

                    // Restore the backup and ensure the excluded files are properly marked as loaded
                    \$GLOBALS['__composer_autoload_files'] = \\array_merge(
                        \$existingComposerAutoloadFiles,
                        \\array_fill_keys([], true)
                    );

                    return \$loader;
                })();

                // Class aliases. For more information see:
                // https://github.com/humbug/php-scoper/blob/master/docs/further-reading.md#class-aliases
                if (!function_exists('humbug_phpscoper_expose_class')) {
                    function humbug_phpscoper_expose_class(\$exposed, \$prefixed) {
                        if (!class_exists(\$exposed, false) && !interface_exists(\$exposed, false) && !trait_exists(\$exposed, false)) {
                            spl_autoload_call(\$prefixed);
                        }
                    }
                }
                humbug_phpscoper_expose_class('Acme\\Foo', '_Box\\Acme\\Foo');

                return \$loader;

                PHP,
        ];

        yield 'Registry with a recorded global function' => [
            self::createSymbolsRegistry(
                [],
                [['foo', '_Box\foo']],
            ),
            '_Box',
            <<<PHP
                <?php

                // @generated by Humbug Box

                \$loader = (static function () {
                    // Backup the autoloaded Composer files
                    \$existingComposerAutoloadFiles = \$GLOBALS['__composer_autoload_files'] ?? [];

                    // @generated by Humbug Box

                    // autoload.php @generated by Composer

                    if (PHP_VERSION_ID < 50600) {
                        echo 'Composer 2.3.0 dropped support for autoloading on PHP <5.6 and you are running '.PHP_VERSION.', please upgrade PHP or use Composer 2.2 LTS via "composer self-update --2.2". Aborting.'.PHP_EOL;
                        exit(1);
                    }

                    require_once __DIR__ . '/composer/autoload_real.php';

                    \$loader = {$composerAutoloaderName}::getLoader();

                    // Ensure InstalledVersions is available
                    \$installedVersionsPath = __DIR__.'/composer/InstalledVersions.php';
                    if (file_exists(\$installedVersionsPath)) require_once \$installedVersionsPath;

                    // Restore the backup and ensure the excluded files are properly marked as loaded
                    \$GLOBALS['__composer_autoload_files'] = \\array_merge(
                        \$existingComposerAutoloadFiles,
                        \\array_fill_keys([], true)
                    );

                    return \$loader;
                })();

                // Function aliases. For more information see:
                // https://github.com/humbug/php-scoper/blob/master/docs/further-reading.md#function-aliases
                if (!function_exists('foo')) { function foo() { return \\_Box\\foo(...func_get_args()); } }

                return \$loader;

                PHP,
        ];

        yield 'Registry with recorded namespaced function' => [
            self::createSymbolsRegistry(
                [],
                [
                    ['foo', '_Box\foo'],
                    ['Acme\bar', '_Box\Acme\bar'],
                ],
            ),
            '_Box',
            <<<PHP
                <?php

                // @generated by Humbug Box

                namespace {
                    \$loader = (static function () {
                        // Backup the autoloaded Composer files
                        \$existingComposerAutoloadFiles = \$GLOBALS['__composer_autoload_files'] ?? [];

                        // @generated by Humbug Box

                        // autoload.php @generated by Composer

                        if (PHP_VERSION_ID < 50600) {
                            echo 'Composer 2.3.0 dropped support for autoloading on PHP <5.6 and you are running '.PHP_VERSION.', please upgrade PHP or use Composer 2.2 LTS via "composer self-update --2.2". Aborting.'.PHP_EOL;
                            exit(1);
                        }

                        require_once __DIR__ . '/composer/autoload_real.php';

                        \$loader = {$composerAutoloaderName}::getLoader();

                        // Ensure InstalledVersions is available
                        \$installedVersionsPath = __DIR__.'/composer/InstalledVersions.php';
                        if (file_exists(\$installedVersionsPath)) require_once \$installedVersionsPath;

                        // Restore the backup and ensure the excluded files are properly marked as loaded
                        \$GLOBALS['__composer_autoload_files'] = \\array_merge(
                            \$existingComposerAutoloadFiles,
                            \\array_fill_keys([], true)
                        );

                        return \$loader;
                    })();
                }

                // Function aliases. For more information see:
                // https://github.com/humbug/php-scoper/blob/master/docs/further-reading.md#function-aliases
                namespace Acme {
                    if (!function_exists('Acme\\bar')) { function bar() { return \\_Box\\Acme\\bar(...func_get_args()); } }
                }

                namespace {
                    if (!function_exists('foo')) { function foo() { return \\_Box\\foo(...func_get_args()); } }
                }

                namespace {
                    return \$loader;
                }

                PHP,
        ];

        yield 'Registry with recorded classes and functions' => [
            self::createSymbolsRegistry(
                [
                    ['PHPUnit\TestCase', '_Box\PHPUnit\TestCase'],
                    ['PHPUnit\Framework', '_Box\PHPUnit\Framework'],
                ],
                [
                    ['bar', '_Box\bar'],
                    ['Acme\bar', '_Box\Acme\bar'],
                ],
            ),
            '_Box',
            <<<PHP
                <?php

                // @generated by Humbug Box

                namespace {
                    \$loader = (static function () {
                        // Backup the autoloaded Composer files
                        \$existingComposerAutoloadFiles = \$GLOBALS['__composer_autoload_files'] ?? [];

                        // @generated by Humbug Box

                        // autoload.php @generated by Composer

                        if (PHP_VERSION_ID < 50600) {
                            echo 'Composer 2.3.0 dropped support for autoloading on PHP <5.6 and you are running '.PHP_VERSION.', please upgrade PHP or use Composer 2.2 LTS via "composer self-update --2.2". Aborting.'.PHP_EOL;
                            exit(1);
                        }

                        require_once __DIR__ . '/composer/autoload_real.php';

                        \$loader = {$composerAutoloaderName}::getLoader();

                        // Ensure InstalledVersions is available
                        \$installedVersionsPath = __DIR__.'/composer/InstalledVersions.php';
                        if (file_exists(\$installedVersionsPath)) require_once \$installedVersionsPath;

                        // Restore the backup and ensure the excluded files are properly marked as loaded
                        \$GLOBALS['__composer_autoload_files'] = \\array_merge(
                            \$existingComposerAutoloadFiles,
                            \\array_fill_keys([], true)
                        );

                        return \$loader;
                    })();
                }

                // Class aliases. For more information see:
                // https://github.com/humbug/php-scoper/blob/master/docs/further-reading.md#class-aliases
                namespace {
                    if (!function_exists('humbug_phpscoper_expose_class')) {
                        function humbug_phpscoper_expose_class(\$exposed, \$prefixed) {
                            if (!class_exists(\$exposed, false) && !interface_exists(\$exposed, false) && !trait_exists(\$exposed, false)) {
                                spl_autoload_call(\$prefixed);
                            }
                        }
                    }
                    humbug_phpscoper_expose_class('PHPUnit\\TestCase', '_Box\\PHPUnit\\TestCase');
                    humbug_phpscoper_expose_class('PHPUnit\\Framework', '_Box\\PHPUnit\\Framework');
                }

                // Function aliases. For more information see:
                // https://github.com/humbug/php-scoper/blob/master/docs/further-reading.md#function-aliases
                namespace Acme {
                    if (!function_exists('Acme\\bar')) { function bar() { return \\_Box\\Acme\\bar(...func_get_args()); } }
                }

                namespace {
                    if (!function_exists('bar')) { function bar() { return \\_Box\\bar(...func_get_args()); } }
                }

                namespace {
                    return \$loader;
                }

                PHP,
        ];

        yield 'Registry with recorded symbols and no prefix (it is ignored)' => [
            self::createSymbolsRegistry(
                [],
                [
                    ['bar', '_Box\bar'],
                    ['Acme\bar', '_Box\Acme\bar'],
                ],
            ),
            '',
            <<<PHP
                <?php

                // autoload.php @generated by Composer

                if (PHP_VERSION_ID < 50600) {
                    echo 'Composer 2.3.0 dropped support for autoloading on PHP <5.6 and you are running '.PHP_VERSION.', please upgrade PHP or use Composer 2.2 LTS via "composer self-update --2.2". Aborting.'.PHP_EOL;
                    exit(1);
                }

                require_once __DIR__ . '/composer/autoload_real.php';

                return {$composerAutoloaderName}::getLoader();

                PHP,
        ];
    }
}
