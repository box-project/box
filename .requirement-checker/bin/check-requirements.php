<?php

namespace _HumbugBoxaa731ba336da\KevinGH\RequirementChecker;

require __DIR__ . '/../vendor/autoload.php';
if (\false === \in_array(\PHP_SAPI, array('cli', 'phpdbg', 'embed'), \true)) {
    echo \PHP_EOL . 'The application may only be invoked from a command line, got "' . \PHP_SAPI . '"' . \PHP_EOL;
    exit(1);
}
$checkPassed = \_HumbugBoxaa731ba336da\KevinGH\RequirementChecker\Checker::checkRequirements();
if (\false === $checkPassed) {
    exit(1);
}
