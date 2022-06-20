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

use Generator;
use PHPUnit\Framework\TestCase;
use function preg_replace;
use const PHP_VERSION;

/**
 * @covers \KevinGH\RequirementChecker\Checker
 */
class CheckerTest extends TestCase
{
    /**
     * @dataProvider provideRequirements
     */
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

        $this->assertSame($expectedOutput, $actualOutput);
        $this->assertSame($expectedResult, $actualResult);
    }

    public function provideRequirements(): Generator
    {
        $phpVersion = PHP_VERSION;

        yield (static function () use ($phpVersion) {
            return [
                new RequirementCollection(),
                IO::VERBOSITY_DEBUG,
                true,
                <<<EOF

Box Requirements Checker
========================

> Using PHP $phpVersion
> PHP is using the following php.ini file:
  /path/to/php.ini

> No requirements found.


 [OK] Your system is ready to run the application.



EOF
            ];
        })();

        yield (static function () use ($phpVersion) {
            return [
                new RequirementCollection(),
                IO::VERBOSITY_VERY_VERBOSE,
                true,
                <<<EOF

Box Requirements Checker
========================

> Using PHP $phpVersion
> PHP is using the following php.ini file:
  /path/to/php.ini

> No requirements found.


 [OK] Your system is ready to run the application.



EOF
            ];
        })();

        foreach ([IO::VERBOSITY_VERBOSE, IO::VERBOSITY_NORMAL, IO::VERBOSITY_QUIET] as $verbosity) {
            yield (static function () use ($verbosity) {
                return [
                    new RequirementCollection(),
                    $verbosity,
                    true,
                    '',
                ];
            })();
        }

        yield (static function () use ($phpVersion) {
            $requirements = new RequirementCollection();

            $requirements->addRequirement(
                new ConditionIsFulfilled(),
                'The application requires the version "7.2.0" or greater. Got "7.2.2"',
                'The application requires the version "7.2.0" or greater.'
            );
            $requirements->addRequirement(
                new class implements IsFulfilled {
                    public function __invoke(): bool
                    {
                        return true;
                    }
                },
                'The package "acme/foo" requires the extension "random". Enable it or install a polyfill.',
                'The package "acme/foo" requires the extension "random".'
            );

            return [
                $requirements,
                IO::VERBOSITY_DEBUG,
                true,
                <<<EOF

Box Requirements Checker
========================

> Using PHP $phpVersion
> PHP is using the following php.ini file:
  /path/to/php.ini

> Checking Box requirements:
  ✔ The application requires the version "7.2.0" or greater.
  ✔ The package "acme/foo" requires the extension "random".


 [OK] Your system is ready to run the application.



EOF
            ];
        })();

        yield (static function () use ($phpVersion) {
            $requirements = new RequirementCollection();

            $requirements->addRequirement(
                new ConditionIsFulfilled(),
                'The application requires the version "7.2.0" or greater. Got "7.2.2"',
                'The application requires the version "7.2.0" or greater.'
            );
            $requirements->addRequirement(
                new ConditionIsFulfilled(),
                'The package "acme/foo" requires the extension "random". Enable it or install a polyfill.',
                'The package "acme/foo" requires the extension "random".'
            );

            return [
                $requirements,
                IO::VERBOSITY_VERY_VERBOSE,
                true,
                <<<EOF

Box Requirements Checker
========================

> Using PHP $phpVersion
> PHP is using the following php.ini file:
  /path/to/php.ini

> Checking Box requirements:
  ..


 [OK] Your system is ready to run the application.



EOF
            ];
        })();

        foreach ([IO::VERBOSITY_VERBOSE, IO::VERBOSITY_NORMAL, IO::VERBOSITY_QUIET] as $verbosity) {
            yield (static function () use ($verbosity) {
                $requirements = new RequirementCollection();

                $requirements->addRequirement(
                    new ConditionIsFulfilled(),
                    'The application requires the version "7.2.0" or greater. Got "7.2.2"',
                    'The application requires the version "7.2.0" or greater.'
                );
                $requirements->addRequirement(
                    new ConditionIsFulfilled(),
                    'The package "acme/foo" requires the extension "random". Enable it or install a polyfill.',
                    'The package "acme/foo" requires the extension "random".'
                );

                return [
                    $requirements,
                    $verbosity,
                    true,
                    '',
                ];
            })();
        }

        yield (static function () use ($phpVersion) {
            $requirements = new RequirementCollection();

            $requirements->addRequirement(
                new ConditionIsFulfilled(),
                'The application requires the version "7.2.0" or greater. Got "7.2.2"',
                'The application requires the version "7.2.0" or greater.'
            );
            $requirements->addRequirement(
                new ConditionIsNotFulfilled(),
                'The package "acme/foo" requires the extension "random". Enable it or install a polyfill.',
                'The package "acme/foo" requires the extension "random".'
            );

            return [
                $requirements,
                IO::VERBOSITY_DEBUG,
                false,
                <<<EOF

Box Requirements Checker
========================

> Using PHP $phpVersion
> PHP is using the following php.ini file:
  /path/to/php.ini

> Checking Box requirements:
  ✔ The application requires the version "7.2.0" or greater.
  ✘ The package "acme/foo" requires the extension "random". Enable it or install
a polyfill.


 [ERROR] Your system is not ready to run the application.


Fix the following mandatory requirements:
=========================================

 * The package "acme/foo" requires the extension "random". Enable it or install
   a polyfill.


EOF
            ];
        })();

        foreach ([IO::VERBOSITY_VERY_VERBOSE, IO::VERBOSITY_VERBOSE, IO::VERBOSITY_NORMAL] as $verbosity) {
            yield (static function () use ($verbosity, $phpVersion) {
                $requirements = new RequirementCollection();

                $requirements->addRequirement(
                    new ConditionIsFulfilled(),
                    'The application requires the version "7.2.0" or greater. Got "7.2.2"',
                    'The application requires the version "7.2.0" or greater.'
                );
                $requirements->addRequirement(
                    new ConditionIsNotFulfilled(),
                    'The package "acme/foo" requires the extension "random". Enable it or install a polyfill.',
                    'The package "acme/foo" requires the extension "random".'
                );

                return [
                    $requirements,
                    $verbosity,
                    false,
                    <<<EOF

Box Requirements Checker
========================

> Using PHP $phpVersion
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

        yield (static function () use ($phpVersion) {
            $requirements = new RequirementCollection();

            $requirements->addRequirement(
                new ConditionIsFulfilled(),
                'The application requires the version "7.2.0" or greater. Got "7.2.2"',
                'The application requires the version "7.2.0" or greater.'
            );
            $requirements->addRequirement(
                new ConditionIsNotFulfilled(),
                'The package "acme/foo" requires the extension "random". Enable it or install a polyfill.',
                'The package "acme/foo" requires the extension "random".'
            );

            return [
                $requirements,
                IO::VERBOSITY_QUIET,
                false,
                <<<EOF

Box Requirements Checker
========================

> Using PHP $phpVersion
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
