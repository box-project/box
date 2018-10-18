<?php

namespace _HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker;

require __DIR__ . '/../vendor/autoload.php';
if (\PHP_SAPI !== 'cli' && \PHP_SAPI !== 'phpdbg') {
    echo \PHP_EOL . 'The application may only be invoked from a command line' . \PHP_EOL;
    exit(1);
}
$checkPassed = \_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\Checker::checkRequirements();
if (\false === $checkPassed) {
    exit(1);
}
