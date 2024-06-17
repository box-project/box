<?php

namespace HumbugBox462\KevinGH\RequirementChecker;

if (isset($_SERVER['BOX_REQUIREMENT_CHECKER'])) {
    $enableRequirementChecker = $_SERVER['BOX_REQUIREMENT_CHECKER'];
    if (is_bool($enableRequirementChecker) && !$enableRequirementChecker) {
        return;
    }
    if (is_string($enableRequirementChecker) && in_array(strtolower($enableRequirementChecker), ['false', '0'], \true)) {
        return;
    }
    if (!is_bool($enableRequirementChecker) && !is_string($enableRequirementChecker)) {
        echo \PHP_EOL . 'Unhandled value type for "BOX_REQUIREMENT_CHECKER". Got "' . gettype($enableRequirementChecker) . '". Proceeding with the requirement checks.' . \PHP_EOL;
    }
}
if (\false === in_array(\PHP_SAPI, array('cli', 'phpdbg', 'embed', 'micro'), \true)) {
    echo \PHP_EOL . 'The application may only be invoked from a command line, got "' . \PHP_SAPI . '"' . \PHP_EOL;
    exit(1);
}
require __DIR__ . '/../vendor/autoload.php';
if (!Checker::checkRequirements()) {
    exit(1);
}
