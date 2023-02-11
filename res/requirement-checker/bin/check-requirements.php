<?php

namespace HumbugBox420\KevinGH\RequirementChecker;

if (isset($_SERVER['BOX_REQUIREMENT_CHECKER']) && \false === (bool) $_SERVER['BOX_REQUIREMENT_CHECKER']) {
    return;
}
if (\false === \in_array(\PHP_SAPI, array('cli', 'phpdbg', 'embed', 'micro'), \true)) {
    echo \PHP_EOL . 'The application may only be invoked from a command line, got "' . \PHP_SAPI . '"' . \PHP_EOL;
    exit(1);
}
require __DIR__ . '/../vendor/autoload.php';
if (Checker::checkRequirements()) {
    exit(1);
}
