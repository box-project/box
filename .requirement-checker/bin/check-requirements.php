<?php

namespace _HumbugBox5af37057079ab\KevinGH\RequirementChecker;

require __DIR__ . '/../vendor/autoload.php';
if (\PHP_SAPI !== 'cli' && \PHP_SAPI !== 'phpdbg') {
    echo \PHP_EOL . 'The application may only be invoked from a command line' . \PHP_EOL;
    exit(1);
}
$checkPassed = \_HumbugBox5af37057079ab\KevinGH\RequirementChecker\Checker::checkRequirements();
if (\false === $checkPassed) {
    exit(1);
}
