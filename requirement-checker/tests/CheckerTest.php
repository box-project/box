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

namespace KevinGH\RequirementChecker;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function preg_replace;
use const PHP_VERSION;

/**
 * @internal
 */
#[CoversClass(Checker::class)]
class CheckerTest extends TestCase
{
    #[DataProvider('provideRequirements')]
    public function test_it_can_check_requirements(
        RequirementCollection $requirements,
        int $verbosity,
        bool $expectedResult,
        string $expectedOutput
    ): void {
        $actualResult = $requirements->evaluateRequirements();

        ob_start();
        Checker::printCheck(
            $expectedResult,
            new Printer(
                $verbosity,
                false
            ),
            $requirements
        );

        $actualOutput = ob_get_clean();

        $actualOutput = DisplayNormalizer::removeTrailingSpaces($actualOutput);
        $actualOutput = preg_replace(
            '~/.+/php.ini~',
            '/path/to/php.ini',
            $actualOutput
        );

        self::assertSame($expectedOutput, $actualOutput);
        self::assertSame($expectedResult, $actualResult);
    }

    public static function provideRequirements(): iterable
    {
        $phpVersion = PHP_VERSION;
        $remainingVerbosities = ['verbosity=verbose' => IO::VERBOSITY_VERBOSE, 'verbosity=normal' => IO::VERBOSITY_NORMAL, 'verbosity=quiet' => IO::VERBOSITY_QUIET];

        yield 'no requirement; verbosity=debug' => (static function () use ($phpVersion) {
            return [
                new RequirementCollection(),
                IO::VERBOSITY_DEBUG,
                true,
                <<<EOF

                    Box Requirements Checker
                    ========================

                    > Using PHP {$phpVersion}
                    > PHP is using the following php.ini file:
                      /path/to/php.ini

                    > No requirements found.


                     [OK] Your system is ready to run the application.



                    EOF
            ];
        })();

        yield 'no requirement + no ini path; verbosity=debug' => (static function () use ($phpVersion) {
            return [
                new RequirementCollection(false),
                IO::VERBOSITY_DEBUG,
                true,
                <<<EOF

                    Box Requirements Checker
                    ========================

                    > Using PHP {$phpVersion}
                    > PHP is not using any php.ini file.

                    > No requirements found.


                     [OK] Your system is ready to run the application.



                    EOF
            ];
        })();

        yield 'no requirement; verbosity=very verbose' => (static function () use ($phpVersion) {
            return [
                new RequirementCollection(),
                IO::VERBOSITY_VERY_VERBOSE,
                true,
                <<<EOF

                    Box Requirements Checker
                    ========================

                    > Using PHP {$phpVersion}
                    > PHP is using the following php.ini file:
                      /path/to/php.ini

                    > No requirements found.


                     [OK] Your system is ready to run the application.



                    EOF
            ];
        })();

        foreach ($remainingVerbosities as $label => $verbosity) {
            yield 'no requirements; '.$label => (static function () use ($verbosity) {
                return [
                    new RequirementCollection(),
                    $verbosity,
                    true,
                    '',
                ];
            })();
        }

        yield 'requirements; check passes; verbosity=debug' => (static function () use ($phpVersion) {
            $requirements = new RequirementCollection();

            $requirements->addRequirement(
                new ConditionIsFulfilled(),
                'This application requires the PHP version "7.2.0" or greater.',
                'This application requires the PHP version "7.2.0" or greater. Got "7.2.2"',
            );
            $requirements->addRequirement(
                new class() implements IsFulfilled {
                    public function __invoke(): bool
                    {
                        return true;
                    }
                },
                'The package "acme/foo" requires the extension "random".',
                'The package "acme/foo" requires the extension "random". Enable it or install a polyfill.',
            );

            return [
                $requirements,
                IO::VERBOSITY_DEBUG,
                true,
                <<<EOF

                    Box Requirements Checker
                    ========================

                    > Using PHP {$phpVersion}
                    > PHP is using the following php.ini file:
                      /path/to/php.ini

                    > Checking Box requirements:
                      ✔ This application requires the PHP version "7.2.0" or greater.
                      ✔ The package "acme/foo" requires the extension "random".


                     [OK] Your system is ready to run the application.



                    EOF
            ];
        })();

        yield 'requirements; check passes; verbosity=very verbose' => (static function () use ($phpVersion) {
            $requirements = new RequirementCollection();

            $requirements->addRequirement(
                new ConditionIsFulfilled(),
                'This application requires the PHP version "7.2.0" or greater. Got "7.2.2"',
                'This application requires the PHP version "7.2.0" or greater.'
            );
            $requirements->addRequirement(
                new ConditionIsFulfilled(),
                'The package "acme/foo" requires the extension "random".',
                'The package "acme/foo" requires the extension "random". Enable it or install a polyfill.',
            );

            return [
                $requirements,
                IO::VERBOSITY_VERY_VERBOSE,
                true,
                <<<EOF

                    Box Requirements Checker
                    ========================

                    > Using PHP {$phpVersion}
                    > PHP is using the following php.ini file:
                      /path/to/php.ini

                    > Checking Box requirements:
                      ..


                     [OK] Your system is ready to run the application.



                    EOF
            ];
        })();

        foreach ($remainingVerbosities as $label => $verbosity) {
            yield 'requirements; check passes; '.$label => (static function () use ($verbosity) {
                $requirements = new RequirementCollection();

                $requirements->addRequirement(
                    new ConditionIsFulfilled(),
                    'This application requires the PHP version "7.2.0" or greater. Got "7.2.2"',
                    'This application requires the PHP version "7.2.0" or greater.'
                );
                $requirements->addRequirement(
                    new ConditionIsFulfilled(),
                    'The package "acme/foo" requires the extension "random".',
                    'The package "acme/foo" requires the extension "random". Enable it or install a polyfill.',
                );

                return [
                    $requirements,
                    $verbosity,
                    true,
                    '',
                ];
            })();
        }

        yield 'requirements; check do not pass; verbosity=debug' => (static function () use ($phpVersion) {
            $requirements = new RequirementCollection();

            $requirements->addRequirement(
                new ConditionIsFulfilled(),
                'This application requires the PHP version "7.2.0" or greater. Got "7.2.2"',
                'This application requires the PHP version "7.2.0" or greater.'
            );
            $requirements->addRequirement(
                new ConditionIsNotFulfilled(),
                'The package "acme/foo" requires the extension "random".',
                'The package "acme/foo" requires the extension "random". Enable it or install a polyfill.',
            );

            return [
                $requirements,
                IO::VERBOSITY_DEBUG,
                false,
                <<<EOF

                    Box Requirements Checker
                    ========================

                    > Using PHP {$phpVersion}
                    > PHP is using the following php.ini file:
                      /path/to/php.ini

                    > Checking Box requirements:
                      ✔ This application requires the PHP version "7.2.0" or greater. Got "7.2.2"
                      ✘ The package "acme/foo" requires the extension "random".


                     [ERROR] Your system is not ready to run the application.


                    Fix the following mandatory requirements:
                    =========================================

                     * The package "acme/foo" requires the extension "random". Enable it or install
                       a polyfill.


                    EOF
            ];
        })();

        foreach (['verbosity=very verbose' => IO::VERBOSITY_VERY_VERBOSE, 'verbosity=verbose' => IO::VERBOSITY_VERBOSE, 'verbosity=normal' => IO::VERBOSITY_NORMAL] as $label => $verbosity) {
            yield 'requirements; check do not pass; '.$label => (static function () use ($verbosity, $phpVersion) {
                $requirements = new RequirementCollection();

                $requirements->addRequirement(
                    new ConditionIsFulfilled(),
                    'This application requires the PHP version "7.2.0" or greater. Got "7.2.2"',
                    'This application requires the PHP version "7.2.0" or greater.'
                );
                $requirements->addRequirement(
                    new ConditionIsNotFulfilled(),
                    'The package "acme/foo" requires the extension "random".',
                    'The package "acme/foo" requires the extension "random". Enable it or install a polyfill.',
                );

                return [
                    $requirements,
                    $verbosity,
                    false,
                    <<<EOF

                        Box Requirements Checker
                        ========================

                        > Using PHP {$phpVersion}
                        > PHP is using the following php.ini file:
                          /path/to/php.ini

                        > Checking Box requirements:
                          .E


                         [ERROR] Your system is not ready to run the application.


                        Fix the following mandatory requirements:
                        =========================================

                         * The package "acme/foo" requires the extension "random". Enable it or install
                           a polyfill.


                        EOF
                ];
            })();
        }

        yield 'requirements; check do not pass; verbosity=quiet' => (static function () use ($phpVersion) {
            $requirements = new RequirementCollection();

            $requirements->addRequirement(
                new ConditionIsFulfilled(),
                'This application requires the PHP version "7.2.0" or greater. Got "7.2.2"',
                'This application requires the PHP version "7.2.0" or greater.'
            );
            $requirements->addRequirement(
                new ConditionIsNotFulfilled(),
                'The package "acme/foo" requires the extension "random".',
                'The package "acme/foo" requires the extension "random". Enable it or install a polyfill.',
            );

            return [
                $requirements,
                IO::VERBOSITY_QUIET,
                false,
                <<<EOF

                    Box Requirements Checker
                    ========================

                    > Using PHP {$phpVersion}
                    > PHP is using the following php.ini file:
                      /path/to/php.ini

                    > Checking Box requirements:
                      .E


                     [ERROR] Your system is not ready to run the application.


                    Fix the following mandatory requirements:
                    =========================================

                     * The package "acme/foo" requires the extension "random". Enable it or install
                       a polyfill.


                    EOF
            ];
        })();
    }
}
