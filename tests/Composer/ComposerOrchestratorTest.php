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
use function file_exists;
use function file_get_contents;
use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use function iterator_to_array;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\mirror;
use KevinGH\Box\Test\FileSystemTestCase;
use PhpParser\Node\Name\FullyQualified;
use function preg_replace;
use RuntimeException;
use Symfony\Component\Finder\Finder;

/**
 * @covers \KevinGH\Box\Composer\ComposerOrchestrator
 */
class ComposerOrchestratorTest extends FileSystemTestCase
{
    private const FIXTURES = __DIR__.'/../../fixtures/composer-dump';
    private const COMPOSER_AUTOLOADER_NAME = 'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05';

    /**
     * @dataProvider composerAutoloadProvider
     */
    public function test_it_can_dump_the_autoloader_with_an_empty_composer_json(
        SymbolsRegistry $symbolsRegistry,
        string $prefix,
        string $expectedAutoloadContents,
    ): void {
        dump_file('composer.json', '{}');

        ComposerOrchestrator::dumpAutoload($symbolsRegistry, $prefix, false);

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

        $this->assertSame($expectedPaths, $actualPaths);

        $actualAutoloadContents = preg_replace(
            '/ComposerAutoloaderInit[a-z\d]{32}/',
            'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05',
            file_get_contents($this->tmp.'/vendor/autoload.php'),
        );
        $actualAutoloadContents = DisplayNormalizer::removeTrailingSpaces($actualAutoloadContents);

        $this->assertSame($expectedAutoloadContents, $actualAutoloadContents);

        $this->assertSame(
            <<<'PHP'
                <?php

                // autoload_psr4.php @generated by Composer

                $vendorDir = dirname(__DIR__);
                $baseDir = dirname($vendorDir);

                return array(
                );

                PHP
            ,
            preg_replace(
                '/ComposerAutoloaderInit[a-z\d]{32}/',
                'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05',
                file_get_contents($this->tmp.'/vendor/composer/autoload_psr4.php'),
            ),
        );
    }

    /**
     * @dataProvider composerAutoloadProvider
     */
    public function test_it_cannot_dump_the_autoloader_with_an_invalid_composer_json(
        SymbolsRegistry $symbolsRegistry,
        string $prefix,
    ): void {
        mirror(self::FIXTURES.'/dir000', $this->tmp);

        dump_file('composer.json', '');

        try {
            ComposerOrchestrator::dumpAutoload($symbolsRegistry, $prefix, false);

            $this->fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Could not dump the autoloader.',
                $exception->getMessage(),
            );
            $this->assertSame(0, $exception->getCode());
            $this->assertNotNull($exception->getPrevious());

            $this->assertStringContainsString(
                '"./composer.json" does not contain valid JSON',
                $exception->getPrevious()->getMessage(),
            );
        }
    }

    public function test_it_can_dump_the_autoloader_with_a_composer_json_with_a_dependency(): void
    {
        mirror(self::FIXTURES.'/dir000', $this->tmp);

        ComposerOrchestrator::dumpAutoload(new SymbolsRegistry(), '', false);

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

        $this->assertSame($expectedPaths, $actualPaths);

        $this->assertSame(
            <<<'PHP'
                <?php

                // autoload.php @generated by Composer

                if (PHP_VERSION_ID < 50600) {
                    if (!headers_sent()) {
                        header('HTTP/1.1 500 Internal Server Error');
                    }
                    $err = 'Composer 2.3.0 dropped support for autoloading on PHP <5.6 and you are running '.PHP_VERSION.', please upgrade PHP or use Composer 2.2 LTS via "composer self-update --2.2". Aborting.'.PHP_EOL;
                    if (!ini_get('display_errors')) {
                        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
                            fwrite(STDERR, $err);
                        } elseif (!headers_sent()) {
                            echo $err;
                        }
                    }
                    trigger_error(
                        $err,
                        E_USER_ERROR
                    );
                }

                require_once __DIR__ . '/composer/autoload_real.php';

                return ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05::getLoader();

                PHP
            ,
            preg_replace(
                '/ComposerAutoloaderInit[a-z\d]{32}/',
                'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05',
                file_get_contents($this->tmp.'/vendor/autoload.php'),
            ),
        );

        $this->assertSame(
            <<<'PHP'
                <?php

                // autoload_psr4.php @generated by Composer

                $vendorDir = dirname(__DIR__);
                $baseDir = dirname($vendorDir);

                return array(
                );

                PHP
            ,
            preg_replace(
                '/ComposerAutoloaderInit[a-z\d]{32}/',
                'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05',
                file_get_contents($this->tmp.'/vendor/composer/autoload_psr4.php'),
            ),
        );
    }

    /**
     * @dataProvider composerAutoloadProvider
     */
    public function test_it_cannot_dump_the_autoloader_if_the_composer_json_file_is_missing(
        SymbolsRegistry $symbolsRegistry,
        string $prefix,
    ): void {
        try {
            ComposerOrchestrator::dumpAutoload($symbolsRegistry, $prefix, false);

            $this->fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Could not dump the autoloader.',
                $exception->getMessage(),
            );
            $this->assertSame(0, $exception->getCode());
            $this->assertNotNull($exception->getPrevious());

            $this->assertStringContainsString(
                'Composer could not find a composer.json file in',
                $exception->getPrevious()->getMessage(),
            );
        }
    }

    /**
     * @dataProvider composerAutoloadProvider
     */
    public function test_it_can_dump_the_autoloader_with_a_composer_json_lock_and_installed_with_a_dependency(
        SymbolsRegistry $SymbolsRegistry,
        string $prefix,
        string $expectedAutoloadContents,
    ): void {
        $this->skipIfFixturesNotInstalled(self::FIXTURES.'/dir001/vendor');
        mirror(self::FIXTURES.'/dir001', $this->tmp);

        ComposerOrchestrator::dumpAutoload($SymbolsRegistry, $prefix, false);

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

        $this->assertSame($expectedPaths, $actualPaths);

        $this->assertSame(
            $expectedAutoloadContents,
            preg_replace(
                '/ComposerAutoloaderInit[a-z\d]{32}/',
                'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05',
                file_get_contents($this->tmp.'/vendor/autoload.php'),
            ),
        );

        $this->assertSame(
            <<<'PHP'
                <?php

                // autoload_psr4.php @generated by Composer

                $vendorDir = dirname(__DIR__);
                $baseDir = dirname($vendorDir);

                return array(
                    'Assert\\' => array($vendorDir . '/beberlei/assert/lib/Assert'),
                );

                PHP
            ,
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
        mirror(self::FIXTURES.'/dir003', $this->tmp);

        $composerAutoloaderName = self::COMPOSER_AUTOLOADER_NAME;

        $expectedAutoloadContents = <<<PHP
            <?php

            // autoload.php @generated by Composer

            if (PHP_VERSION_ID < 50600) {
                if (!headers_sent()) {
                    header('HTTP/1.1 500 Internal Server Error');
                }
                \$err = 'Composer 2.3.0 dropped support for autoloading on PHP <5.6 and you are running '.PHP_VERSION.', please upgrade PHP or use Composer 2.2 LTS via "composer self-update --2.2". Aborting.'.PHP_EOL;
                if (!ini_get('display_errors')) {
                    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
                        fwrite(STDERR, \$err);
                    } elseif (!headers_sent()) {
                        echo \$err;
                    }
                }
                trigger_error(
                    \$err,
                    E_USER_ERROR
                );
            }

            require_once __DIR__ . '/composer/autoload_real.php';

            return $composerAutoloaderName::getLoader();

            PHP;

        ComposerOrchestrator::dumpAutoload(
            new SymbolsRegistry(),
            '',
            true,
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

        $this->assertSame($expectedPaths, $actualPaths);

        $this->assertSame(
            $expectedAutoloadContents,
            preg_replace(
                '/ComposerAutoloaderInit[a-z\d]{32}/',
                'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05',
                file_get_contents($this->tmp.'/vendor/autoload.php'),
            ),
        );

        $this->assertSame(
            <<<'PHP'
                <?php

                // autoload_psr4.php @generated by Composer

                $vendorDir = dirname(__DIR__);
                $baseDir = dirname($vendorDir);

                return array(
                );

                PHP
            ,
            preg_replace(
                '/ComposerAutoloaderInit[a-z\d]{32}/',
                'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05',
                file_get_contents($this->tmp.'/vendor/composer/autoload_psr4.php'),
            ),
        );
    }

    /**
     * @dataProvider composerAutoloadProvider
     */
    public function test_it_can_dump_the_autoloader_with_a_composer_json_and_lock_with_a_dependency(
        SymbolsRegistry $symbolsRegistry,
        string $prefix,
        string $expectedAutoloadContents,
    ): void {
        $this->skipIfFixturesNotInstalled(self::FIXTURES.'/dir002/vendor');
        mirror(self::FIXTURES.'/dir002', $this->tmp);

        ComposerOrchestrator::dumpAutoload($symbolsRegistry, $prefix, false);

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

        $this->assertEqualsCanonicalizing($expectedPaths, $actualPaths);

        $this->assertSame(
            $expectedAutoloadContents,
            preg_replace(
                '/ComposerAutoloaderInit[a-z\d]{32}/',
                'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05',
                file_get_contents($this->tmp.'/vendor/autoload.php'),
            ),
        );

        $this->assertSame(
            <<<'PHP'
                <?php

                // autoload_psr4.php @generated by Composer

                $vendorDir = dirname(__DIR__);
                $baseDir = dirname($vendorDir);

                return array(
                    'Assert\\' => array($vendorDir . '/beberlei/assert/lib/Assert'),
                );

                PHP
            ,
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
                    if (!headers_sent()) {
                        header('HTTP/1.1 500 Internal Server Error');
                    }
                    \$err = 'Composer 2.3.0 dropped support for autoloading on PHP <5.6 and you are running '.PHP_VERSION.', please upgrade PHP or use Composer 2.2 LTS via "composer self-update --2.2". Aborting.'.PHP_EOL;
                    if (!ini_get('display_errors')) {
                        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
                            fwrite(STDERR, \$err);
                        } elseif (!headers_sent()) {
                            echo \$err;
                        }
                    }
                    trigger_error(
                        \$err,
                        E_USER_ERROR
                    );
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

                // Backup the autoloaded Composer files
                if (isset(\$GLOBALS['__composer_autoload_files'])) {
                    \$existingComposerAutoloadFiles = \$GLOBALS['__composer_autoload_files'];
                }

                // autoload.php @generated by Composer

                if (PHP_VERSION_ID < 50600) {
                    if (!headers_sent()) {
                        header('HTTP/1.1 500 Internal Server Error');
                    }
                    \$err = 'Composer 2.3.0 dropped support for autoloading on PHP <5.6 and you are running '.PHP_VERSION.', please upgrade PHP or use Composer 2.2 LTS via "composer self-update --2.2". Aborting.'.PHP_EOL;
                    if (!ini_get('display_errors')) {
                        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
                            fwrite(STDERR, \$err);
                        } elseif (!headers_sent()) {
                            echo \$err;
                        }
                    }
                    trigger_error(
                        \$err,
                        E_USER_ERROR
                    );
                }

                require_once __DIR__ . '/composer/autoload_real.php';

                \$loader = {$composerAutoloaderName}::getLoader();

                // Ensure InstalledVersions is available
                require_once __DIR__.'/composer/InstalledVersions.php';

                // Restore the backup
                if (isset(\$existingComposerAutoloadFiles)) {
                    \$GLOBALS['__composer_autoload_files'] = \$existingComposerAutoloadFiles;
                } else {
                    unset(\$GLOBALS['__composer_autoload_files']);
                }

                // Class aliases. For more information see:
                // https://github.com/humbug/php-scoper/blob/master/docs/further-reading.md#class-aliases
                function humbug_phpscoper_expose_class(string \$exposed, string \$prefixed): void {
                    if (!class_exists(\$exposed, false) && !interface_exists(\$exposed, false) && !trait_exists(\$exposed, false)) {
                        spl_autoload_call(\$prefixed);
                    }
                }
                humbug_phpscoper_expose_class('Acme\Foo', '_Box\Acme\Foo');

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

                // Backup the autoloaded Composer files
                if (isset(\$GLOBALS['__composer_autoload_files'])) {
                    \$existingComposerAutoloadFiles = \$GLOBALS['__composer_autoload_files'];
                }

                // autoload.php @generated by Composer

                if (PHP_VERSION_ID < 50600) {
                    if (!headers_sent()) {
                        header('HTTP/1.1 500 Internal Server Error');
                    }
                    \$err = 'Composer 2.3.0 dropped support for autoloading on PHP <5.6 and you are running '.PHP_VERSION.', please upgrade PHP or use Composer 2.2 LTS via "composer self-update --2.2". Aborting.'.PHP_EOL;
                    if (!ini_get('display_errors')) {
                        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
                            fwrite(STDERR, \$err);
                        } elseif (!headers_sent()) {
                            echo \$err;
                        }
                    }
                    trigger_error(
                        \$err,
                        E_USER_ERROR
                    );
                }

                require_once __DIR__ . '/composer/autoload_real.php';

                \$loader = {$composerAutoloaderName}::getLoader();

                // Ensure InstalledVersions is available
                require_once __DIR__.'/composer/InstalledVersions.php';

                // Restore the backup
                if (isset(\$existingComposerAutoloadFiles)) {
                    \$GLOBALS['__composer_autoload_files'] = \$existingComposerAutoloadFiles;
                } else {
                    unset(\$GLOBALS['__composer_autoload_files']);
                }

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
                    // Backup the autoloaded Composer files
                    if (isset(\$GLOBALS['__composer_autoload_files'])) {
                        \$existingComposerAutoloadFiles = \$GLOBALS['__composer_autoload_files'];
                    }

                // autoload.php @generated by Composer

                if (PHP_VERSION_ID < 50600) {
                    if (!headers_sent()) {
                        header('HTTP/1.1 500 Internal Server Error');
                    }
                    \$err = 'Composer 2.3.0 dropped support for autoloading on PHP <5.6 and you are running '.PHP_VERSION.', please upgrade PHP or use Composer 2.2 LTS via "composer self-update --2.2". Aborting.'.PHP_EOL;
                    if (!ini_get('display_errors')) {
                        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
                            fwrite(STDERR, \$err);
                        } elseif (!headers_sent()) {
                            echo \$err;
                        }
                    }
                    trigger_error(
                        \$err,
                        E_USER_ERROR
                    );
                }

                require_once __DIR__ . '/composer/autoload_real.php';

                \$loader = {$composerAutoloaderName}::getLoader();

                    // Ensure InstalledVersions is available
                    require_once __DIR__.'/composer/InstalledVersions.php';

                    // Restore the backup
                    if (isset(\$existingComposerAutoloadFiles)) {
                        \$GLOBALS['__composer_autoload_files'] = \$existingComposerAutoloadFiles;
                    } else {
                        unset(\$GLOBALS['__composer_autoload_files']);
                    }
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
                    // Backup the autoloaded Composer files
                    if (isset(\$GLOBALS['__composer_autoload_files'])) {
                        \$existingComposerAutoloadFiles = \$GLOBALS['__composer_autoload_files'];
                    }

                // autoload.php @generated by Composer

                if (PHP_VERSION_ID < 50600) {
                    if (!headers_sent()) {
                        header('HTTP/1.1 500 Internal Server Error');
                    }
                    \$err = 'Composer 2.3.0 dropped support for autoloading on PHP <5.6 and you are running '.PHP_VERSION.', please upgrade PHP or use Composer 2.2 LTS via "composer self-update --2.2". Aborting.'.PHP_EOL;
                    if (!ini_get('display_errors')) {
                        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
                            fwrite(STDERR, \$err);
                        } elseif (!headers_sent()) {
                            echo \$err;
                        }
                    }
                    trigger_error(
                        \$err,
                        E_USER_ERROR
                    );
                }

                require_once __DIR__ . '/composer/autoload_real.php';

                \$loader = {$composerAutoloaderName}::getLoader();

                    // Ensure InstalledVersions is available
                    require_once __DIR__.'/composer/InstalledVersions.php';

                    // Restore the backup
                    if (isset(\$existingComposerAutoloadFiles)) {
                        \$GLOBALS['__composer_autoload_files'] = \$existingComposerAutoloadFiles;
                    } else {
                        unset(\$GLOBALS['__composer_autoload_files']);
                    }
                }

                // Class aliases. For more information see:
                // https://github.com/humbug/php-scoper/blob/master/docs/further-reading.md#class-aliases
                namespace {
                    function humbug_phpscoper_expose_class(string \$exposed, string \$prefixed): void {
                        if (!class_exists(\$exposed, false) && !interface_exists(\$exposed, false) && !trait_exists(\$exposed, false)) {
                            spl_autoload_call(\$prefixed);
                        }
                    }
                    humbug_phpscoper_expose_class('PHPUnit\TestCase', '_Box\PHPUnit\TestCase');
                    humbug_phpscoper_expose_class('PHPUnit\Framework', '_Box\PHPUnit\Framework');
                }

                // Function aliases. For more information see:
                // https://github.com/humbug/php-scoper/blob/master/docs/further-reading.md#function-aliases
                namespace Acme {
                    if (!function_exists('Acme\bar')) { function bar() { return \\_Box\\Acme\\bar(...func_get_args()); } }
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
                    if (!headers_sent()) {
                        header('HTTP/1.1 500 Internal Server Error');
                    }
                    \$err = 'Composer 2.3.0 dropped support for autoloading on PHP <5.6 and you are running '.PHP_VERSION.', please upgrade PHP or use Composer 2.2 LTS via "composer self-update --2.2". Aborting.'.PHP_EOL;
                    if (!ini_get('display_errors')) {
                        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
                            fwrite(STDERR, \$err);
                        } elseif (!headers_sent()) {
                            echo \$err;
                        }
                    }
                    trigger_error(
                        \$err,
                        E_USER_ERROR
                    );
                }

                require_once __DIR__ . '/composer/autoload_real.php';

                return {$composerAutoloaderName}::getLoader();

                PHP,
        ];
    }

    /**
     * @param array<array{string, string}> $recordedClasses
     * @param array<array{string, string}> $recordedFunctions
     */
    private static function createSymbolsRegistry(array $recordedClasses = [], array $recordedFunctions = []): SymbolsRegistry
    {
        $registry = new SymbolsRegistry();

        foreach ($recordedClasses as [$original, $alias]) {
            $registry->recordClass(
                new FullyQualified($original),
                new FullyQualified($alias),
            );
        }

        foreach ($recordedFunctions as [$original, $alias]) {
            $registry->recordFunction(
                new FullyQualified($original),
                new FullyQualified($alias),
            );
        }

        return $registry;
    }

    /**
     * @return string[]
     */
    private function retrievePaths(): array
    {
        $finder = Finder::create()->files()->in($this->tmp);

        return $this->normalizePaths(iterator_to_array($finder, false));
    }

    private function skipIfFixturesNotInstalled(string $path): void
    {
        if (!file_exists($path)) {
            $this->markTestSkipped('The fixtures were not installed. Run `$ make test_unit` in order to set them all up.');
        }
    }
}
