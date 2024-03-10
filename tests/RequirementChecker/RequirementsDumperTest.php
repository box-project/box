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

namespace KevinGH\Box\RequirementChecker;

use KevinGH\Box\Composer\Artifact\ComposerJson;
use KevinGH\Box\Composer\Artifact\ComposerLock;
use KevinGH\Box\Console\DisplayNormalizer;
use KevinGH\Box\Phar\CompressionAlgorithm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function array_column;
use function sort;

/**
 * @internal
 */
#[CoversClass(RequirementsDumper::class)]
class RequirementsDumperTest extends TestCase
{
    private RequirementsDumper $dumper;

    protected function setUp(): void
    {
        $this->dumper = new RequirementsDumper(
            new AppRequirementsFactory(),
        );
    }

    #[DataProvider('jsonAndLockContentsProvider')]
    public function test_it_dumps_the_requirement_checker_files(
        ComposerJson $composerJson,
        ComposerLock $composerLock,
        CompressionAlgorithm $compressionAlgorithm,
        string $expectedRequirement,
    ): void {
        $checkFiles = $this->dumper->dump(
            $composerJson,
            $composerLock,
            $compressionAlgorithm,
        );

        sort($checkFiles);

        $expectedFiles = [
            '.requirements.php',
            'bin/check-requirements.php',
            'src/Checker.php',
            'src/IO.php',
            'src/IsExtensionConflictFulfilled.php',
            'src/IsExtensionFulfilled.php',
            'src/IsFulfilled.php',
            'src/IsPhpVersionFulfilled.php',
            'src/Printer.php',
            'src/Requirement.php',
            'src/RequirementCollection.php',
            'src/Terminal.php',
            'vendor/autoload.php',
            'vendor/composer/autoload_classmap.php',
            'vendor/composer/autoload_namespaces.php',
            'vendor/composer/autoload_psr4.php',
            'vendor/composer/autoload_real.php',
            'vendor/composer/autoload_static.php',
            'vendor/composer/ClassLoader.php',
            'vendor/composer/LICENSE',
            'vendor/composer/installed.php',
            'vendor/composer/semver/LICENSE',
            'vendor/composer/semver/src/Comparator.php',
            'vendor/composer/semver/src/CompilingMatcher.php',
            'vendor/composer/semver/src/Constraint/Bound.php',
            'vendor/composer/semver/src/Constraint/Constraint.php',
            'vendor/composer/semver/src/Constraint/ConstraintInterface.php',
            'vendor/composer/semver/src/Constraint/MatchAllConstraint.php',
            'vendor/composer/semver/src/Constraint/MatchNoneConstraint.php',
            'vendor/composer/semver/src/Constraint/MultiConstraint.php',
            'vendor/composer/semver/src/Interval.php',
            'vendor/composer/semver/src/Intervals.php',
            'vendor/composer/semver/src/Semver.php',
            'vendor/composer/semver/src/VersionParser.php',
        ];

        if (file_exists(__DIR__.'/../../res/requirement-checker/vendor/composer/InstalledVersions.php')) {
            $expectedFiles[] = 'vendor/composer/InstalledVersions.php';
        }

        sort($expectedFiles);

        self::assertEqualsCanonicalizing(
            $expectedFiles,
            array_column($checkFiles, 0),
        );

        self::assertSame(
            DisplayNormalizer::removeTrailingSpaces($expectedRequirement),
            DisplayNormalizer::removeTrailingSpaces($checkFiles[0][1]),
        );
    }

    public static function jsonAndLockContentsProvider(): iterable
    {
        yield [
            new ComposerJson('', []),
            new ComposerLock('', []),
            CompressionAlgorithm::NONE,
            <<<'PHP'
                <?php

                return array (
                );
                PHP,
        ];

        yield [
            new ComposerJson('', []),
            new ComposerLock(
                '',
                [
                    'packages' => [
                        [
                            'name' => 'acme/foo',
                            'require' => [
                                'php' => '^7.4',
                                'ext-json' => '*',
                            ],
                        ],
                    ],
                ],
            ),
            CompressionAlgorithm::GZ,
            <<<'PHP'
                <?php

                return array (
                  0 =>
                  array (
                    'type' => 'php',
                    'condition' => '^7.4',
                    'source' => 'acme/foo',
                    'message' => 'The package "acme/foo" requires a PHP version matching "^7.4".',
                    'helpMessage' => 'The package "acme/foo" requires a PHP version matching "^7.4".',
                  ),
                  1 =>
                  array (
                    'type' => 'extension',
                    'condition' => 'json',
                    'source' => 'acme/foo',
                    'message' => 'The package "acme/foo" requires the extension "json".',
                    'helpMessage' => 'The package "acme/foo" requires the extension "json". You either need to enable it or request the application to be shipped with a polyfill for this extension.',
                  ),
                  2 =>
                  array (
                    'type' => 'extension',
                    'condition' => 'zlib',
                    'source' => NULL,
                    'message' => 'This application requires the extension "zlib".',
                    'helpMessage' => 'This application requires the extension "zlib". You either need to enable it or request the application to be shipped with a polyfill for this extension.',
                  ),
                );
                PHP,
        ];
    }
}
