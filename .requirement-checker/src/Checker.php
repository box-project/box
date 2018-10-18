<?php

namespace _HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker;

/**
@private
@see
@license
*/
final class Checker
{
    /**
    @var */
    private static $requirementsConfig;
    /**
    @return
    */
    public static function checkRequirements()
    {
        $requirements = self::retrieveRequirements();
        $checkPassed = $requirements->evaluateRequirements();
        $io = new \_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\IO();
        self::printCheck($checkPassed, new \_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\Printer($io->getVerbosity(), $io->hasColorSupport()), $requirements);
        return $checkPassed;
    }
    public static function printCheck($checkPassed, \_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\Printer $printer, \_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\RequirementCollection $requirements)
    {
        if (\false === $checkPassed && \_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\IO::VERBOSITY_VERY_VERBOSE > $printer->getVerbosity()) {
            $printer->setVerbosity(\_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\IO::VERBOSITY_VERY_VERBOSE);
        }
        $verbosity = \_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\IO::VERBOSITY_VERY_VERBOSE;
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
                if (\_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\IO::VERBOSITY_DEBUG === $printer->getVerbosity()) {
                    $printer->printvln('✘ ' . $requirement->getTestMessage(), \_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\IO::VERBOSITY_DEBUG, 'red');
                    $printer->printv('  ', \_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\IO::VERBOSITY_DEBUG);
                    $errorMessages[] = $errorMessage;
                } else {
                    $printer->printv('E', $verbosity, 'red');
                    $errorMessages[] = $errorMessage;
                }
                continue;
            }
            if (\_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\IO::VERBOSITY_DEBUG === $printer->getVerbosity()) {
                $printer->printvln('✔ ' . $requirement->getHelpText(), \_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\IO::VERBOSITY_DEBUG, 'green');
                $printer->printv('  ', \_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\IO::VERBOSITY_DEBUG);
            } else {
                $printer->printv('.', $verbosity, 'green');
            }
        }
        if (\_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\IO::VERBOSITY_DEBUG !== $printer->getVerbosity() && \count($requirements) > 0) {
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
    @return
    */
    private static function retrieveRequirements()
    {
        if (null === self::$requirementsConfig) {
            self::$requirementsConfig = __DIR__ . '/../.requirements.php';
        }
        /**
        @var */
        $config = (require self::$requirementsConfig);
        $requirements = new \_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\RequirementCollection();
        foreach ($config as $constraint) {
            $requirements->addRequirement('php' === $constraint['type'] ? new \_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\IsPhpVersionFulfilled($constraint['condition']) : new \_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\IsExtensionFulfilled($constraint['condition']), $constraint['message'], $constraint['helpMessage']);
        }
        return $requirements;
    }
}
