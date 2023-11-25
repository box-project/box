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

use InvalidArgumentException;
use function count;
use function sprintf;

/**
 * @private
 *
 * @see bin/requirements-checker.php
 *
 * @license MIT (c) Fabien Potencier <fabien@symfony.com>
 */
final class Checker
{
    /** @var null|string */
    private static $requirementsConfig;

    public static function checkRequirements(): bool
    {
        $requirements = self::retrieveRequirements();

        $checkPassed = $requirements->evaluateRequirements();

        $io = new IO();

        self::printCheck(
            $checkPassed,
            new Printer(
                $io->getVerbosity(),
                $io->hasColorSupport()
            ),
            $requirements
        );

        return $checkPassed;
    }

    public static function printCheck($checkPassed, Printer $printer, RequirementCollection $requirements): void
    {
        if (false === $checkPassed && IO::VERBOSITY_VERY_VERBOSE > $printer->getVerbosity()) {
            // Override the default verbosity to output errors regardless of the verbosity asked by the user
            $printer->setVerbosity(IO::VERBOSITY_VERY_VERBOSE);
        }

        $verbosity = IO::VERBOSITY_VERY_VERBOSE;

        $iniPath = $requirements->getPhpIniPath();

        $printer->title('Box Requirements Checker', $verbosity);

        $printer->printv('> Using PHP ', $verbosity);
        $printer->printvln(PHP_VERSION, $verbosity, 'green');

        if ($iniPath) {
            $printer->printvln('> PHP is using the following php.ini file:', $verbosity);
            $printer->printvln('  '.$iniPath, $verbosity, 'green');
        } else {
            $printer->printvln('> PHP is not using any php.ini file.', $verbosity, 'yellow');
        }

        $printer->printvln('', $verbosity);

        if (count($requirements) > 0) {
            $printer->printvln('> Checking Box requirements:', $verbosity);
            $printer->printv('  ', $verbosity);
        } else {
            $printer->printvln('> No requirements found.', $verbosity);
        }

        $errorMessages = [];

        foreach ($requirements->getRequirements() as $requirement) {
            if ($errorMessage = $printer->getRequirementErrorMessage($requirement)) {
                if (IO::VERBOSITY_DEBUG === $printer->getVerbosity()) {
                    $printer->printvln('✘ '.$requirement->getTestMessage(), IO::VERBOSITY_DEBUG, 'red');
                    $printer->printv('  ', IO::VERBOSITY_DEBUG);
                    $errorMessages[] = $errorMessage;
                } else {
                    $printer->printv('E', $verbosity, 'red');
                    $errorMessages[] = $errorMessage;
                }

                continue;
            }

            if (IO::VERBOSITY_DEBUG === $printer->getVerbosity()) {
                $printer->printvln('✔ '.$requirement->getTestMessage(), IO::VERBOSITY_DEBUG, 'green');
                $printer->printv('  ', IO::VERBOSITY_DEBUG);
            } else {
                $printer->printv('.', $verbosity, 'green');
            }
        }

        if (IO::VERBOSITY_DEBUG !== $printer->getVerbosity() && count($requirements) > 0) {
            $printer->printvln('', $verbosity);
        }

        if ($requirements->evaluateRequirements()) {
            $printer->block('OK', 'Your system is ready to run the application.', $verbosity, 'success');
        } else {
            $printer->block('ERROR', 'Your system is not ready to run the application.', $verbosity, 'error');

            $printer->title('Fix the following mandatory requirements:', $verbosity, 'red');

            foreach ($errorMessages as $errorMessage) {
                $printer->printv(' * '.$errorMessage, $verbosity);
            }
        }

        $printer->printvln('', $verbosity);
    }

    private static function retrieveRequirements(): RequirementCollection
    {
        if (null === self::$requirementsConfig) {
            self::$requirementsConfig = __DIR__.'/../.requirements.php';
        }

        /** @var list<array{type:string, condition:string, message:string, helpMessage:string}> $config */
        $config = require self::$requirementsConfig;

        $requirements = new RequirementCollection();

        foreach ($config as $constraint) {
            $requirements->addRequirement(
                self::createCondition(
                    $constraint['type'],
                    $constraint['condition']
                ),
                $constraint['message'],
                $constraint['helpMessage']
            );
        }

        return $requirements;
    }

    /**
     * @param string $type
     * @param string $condition
     */
    private static function createCondition($type, $condition): IsFulfilled
    {
        switch ($type) {
            case 'php':
                return new IsPhpVersionFulfilled($condition);

            case 'extension':
                return new IsExtensionFulfilled($condition);

            case 'extension-conflict':
                return new IsExtensionConflictFulfilled($condition);

            default:
                throw new InvalidArgumentException(
                    sprintf(
                        'Unknown requirement type "%s".',
                        $type
                    )
                );
        }
    }
}
