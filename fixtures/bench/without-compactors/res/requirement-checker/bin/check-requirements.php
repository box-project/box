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

namespace HumbugBox451\KevinGH\RequirementChecker;

use function gettype;
use function in_array;
use function is_bool;
use function is_string;
use const false;
use const PHP_EOL;
use const PHP_SAPI;
use const true;

if (isset($_SERVER['BOX_REQUIREMENT_CHECKER'])) {
    $enableRequirementChecker = $_SERVER['BOX_REQUIREMENT_CHECKER'];
    if (is_bool($enableRequirementChecker) && !$enableRequirementChecker) {
        return;
    }
    if (is_string($enableRequirementChecker) && in_array(mb_strtolower($enableRequirementChecker), ['false', '0'], true)) {
        return;
    }
    if (!is_bool($enableRequirementChecker) && !is_string($enableRequirementChecker)) {
        echo PHP_EOL.'Unhandled value type for "BOX_REQUIREMENT_CHECKER". Got "'.gettype($enableRequirementChecker).'". Proceeding with the requirement checks.'.PHP_EOL;
    }
}
if (false === in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed', 'micro'], true)) {
    echo PHP_EOL.'The application may only be invoked from a command line, got "'.PHP_SAPI.'"'.PHP_EOL;
    exit(1);
}
require __DIR__.'/../vendor/autoload.php';
if (!Checker::checkRequirements()) {
    exit(1);
}
