<?php

/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace _HumbugBox5addf3ce683e7\KevinGH\RequirementChecker;

/**
 * The code in this file must be PHP 5.3+ compatible as is used to know if the application can be run.
 *
 * @private
 */
final class Checker
{
    /** @var null|string */
    private static $requirementsConfig;
    /**
     * @return bool
     */
    public static function checkRequirements()
    {
        $requirements = self::retrieveRequirements();
        $checkPassed = $requirements->evaluateRequirements();
        $io = new \_HumbugBox5addf3ce683e7\KevinGH\RequirementChecker\IO();
        self::printCheck($checkPassed, new \_HumbugBox5addf3ce683e7\KevinGH\RequirementChecker\Printer($io->getVerbosity(), $io->hasColorSupport()), $requirements);
        return $checkPassed;
    }
    public static function printCheck($checkPassed, \_HumbugBox5addf3ce683e7\KevinGH\RequirementChecker\Printer $printer, \_HumbugBox5addf3ce683e7\KevinGH\RequirementChecker\RequirementCollection $requirements)
    {
        if (\false === $checkPassed && \_HumbugBox5addf3ce683e7\KevinGH\RequirementChecker\IO::VERBOSITY_VERY_VERBOSE > $printer->getVerbosity()) {
            // Override the default verbosity to output errors regardless of the verbosity asked by the user
            $printer->setVerbosity(\_HumbugBox5addf3ce683e7\KevinGH\RequirementChecker\IO::VERBOSITY_VERY_VERBOSE);
        }
        $verbosity = \_HumbugBox5addf3ce683e7\KevinGH\RequirementChecker\IO::VERBOSITY_VERY_VERBOSE;
        $iniPath = $requirements->getPhpIniPath();
        $printer->title('Box Requirements Checker', $verbosity);
        $printer->printv('> Using PHP ', $verbosity);
        $printer->printvln(\PHP_VERSION, $verbosity, 'green');
        $printer->printvln('> PHP is using the following php.ini file:', $verbosity);
        if ($iniPath) {
            $printer->printvln('  ' . $iniPath, $verbosity, 'green');
        } else {
            $printer->printvln('  WARNING: No configuration file (php.ini) used by PHP!', $verbosity, 'yellow');
        }
        $printer->printvln('', $verbosity);
        if (\count($requirements) > 0) {
            $printer->printvln('> Checking Box requirements:', $verbosity);
            $printer->printv('  ', $verbosity);
        } else {
            $printer->printvln('> No requirements found.', $verbosity);
        }
        $errorMessages = array();
        foreach ($requirements->getRequirements() as $requirement) {
            if ($errorMessage = $printer->getRequirementErrorMessage($requirement)) {
                if (\_HumbugBox5addf3ce683e7\KevinGH\RequirementChecker\IO::VERBOSITY_DEBUG === $printer->getVerbosity()) {
                    $printer->printvln('✘ ' . $requirement->getTestMessage(), \_HumbugBox5addf3ce683e7\KevinGH\RequirementChecker\IO::VERBOSITY_DEBUG, 'red');
                    $printer->printv('  ', \_HumbugBox5addf3ce683e7\KevinGH\RequirementChecker\IO::VERBOSITY_DEBUG);
                    $errorMessages[] = $errorMessage;
                } else {
                    $printer->printv('E', $verbosity, 'red');
                    $errorMessages[] = $errorMessage;
                }
                continue;
            }
            if (\_HumbugBox5addf3ce683e7\KevinGH\RequirementChecker\IO::VERBOSITY_DEBUG === $printer->getVerbosity()) {
                $printer->printvln('✔ ' . $requirement->getHelpText(), \_HumbugBox5addf3ce683e7\KevinGH\RequirementChecker\IO::VERBOSITY_DEBUG, 'green');
                $printer->printv('  ', \_HumbugBox5addf3ce683e7\KevinGH\RequirementChecker\IO::VERBOSITY_DEBUG);
            } else {
                $printer->printv('.', $verbosity, 'green');
            }
        }
        if (\_HumbugBox5addf3ce683e7\KevinGH\RequirementChecker\IO::VERBOSITY_DEBUG !== $printer->getVerbosity() && \count($requirements) > 0) {
            $printer->printvln('', $verbosity);
        }
        if ($requirements->evaluateRequirements()) {
            $printer->block('OK', 'Your system is ready to run the application.', $verbosity, 'success');
        } else {
            $printer->block('ERROR', 'Your system is not ready to run the application.', $verbosity, 'error');
            $printer->title('Fix the following mandatory requirements:', $verbosity, 'red');
            foreach ($errorMessages as $errorMessage) {
                $printer->printv(' * ' . $errorMessage, $verbosity);
            }
        }
        $printer->printvln('', $verbosity);
    }
    /**
     * @return RequirementCollection
     */
    private static function retrieveRequirements()
    {
        if (null === self::$requirementsConfig) {
            self::$requirementsConfig = __DIR__ . '/../.requirements.php';
        }
        $config = (require self::$requirementsConfig);
        $requirements = new \_HumbugBox5addf3ce683e7\KevinGH\RequirementChecker\RequirementCollection();
        foreach ($config as $constraint) {
            \call_user_func_array(array($requirements, 'addRequirement'), $constraint);
        }
        return $requirements;
    }
}
