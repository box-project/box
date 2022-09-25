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

use function array_column;
use KevinGH\Box\Console\DisplayNormalizer;
use Phar;
use PHPUnit\Framework\TestCase;
use function sort;

/**
 * @covers \KevinGH\Box\RequirementChecker\RequirementsDumper
 */
class RequirementsDumperTest extends TestCase
{
    /**
     * @dataProvider jsonAndLockContentsProvider
     */
    public function test_it_dumps_the_requirement_checker_files(
        array $decodedComposerJsonContents,
        array $decodedComposerLockContents,
        ?int $compressionAlgorithm,
        string $expectedRequirement,
    ): void {
        $checkFiles = RequirementsDumper::dump($decodedComposerJsonContents, $decodedComposerLockContents, $compressionAlgorithm);

        sort($checkFiles);

        $expectedFiles = [
            '.requirements.php',
            'bin/check-requirements.php',
            'src/Checker.php',
            'src/IO.php',
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

        if (file_exists(__DIR__.'/../../.requirement-checker/vendor/composer/InstalledVersions.php')) {
            $expectedFiles[] = 'vendor/composer/InstalledVersions.php';
        }

        sort($expectedFiles);

        $this->assertEqualsCanonicalizing(
            $expectedFiles,
            array_column($checkFiles, 0),
        );

        $this->assertSame(
            DisplayNormalizer::removeTrailingSpaces($expectedRequirement),
            DisplayNormalizer::removeTrailingSpaces($checkFiles[0][1]),
        );
    }

    public static function jsonAndLockContentsProvider(): iterable
    {
        yield [
            [],
            [],
            null,
            <<<'PHP'
                <?php

                return array (
                );
                PHP,
        ];

        yield [
            [],
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
            Phar::GZ,
            <<<'PHP'
                <?php

                return array (
                  0 =>
                  array (
                    'type' => 'php',
                    'condition' => '^7.4',
                    'message' => 'The package "acme/foo" requires the version "^7.4" or greater.',
                    'helpMessage' => 'The package "acme/foo" requires the version "^7.4" or greater.',
                  ),
                  1 =>
                  array (
                    'type' => 'extension',
                    'condition' => 'zlib',
                    'message' => 'The application requires the extension "zlib". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "zlib".',
                  ),
                  2 =>
                  array (
                    'type' => 'extension',
                    'condition' => 'json',
                    'message' => 'The package "acme/foo" requires the extension "json". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "acme/foo" requires the extension "json".',
                  ),
                );
                PHP,
        ];
    }
}
