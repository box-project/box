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

namespace KevinGH\RequirementChecker;

require __DIR__.'/../vendor/autoload.php';

if (\PHP_SAPI !== 'cli' && \PHP_SAPI !== 'phpdbg') {
    echo \PHP_EOL.'The application may only be invoked from a command line'.\PHP_EOL;

    exit(1);
}

$checkPassed = Checker::checkRequirements();

if (false === $checkPassed) {
    exit(1);
}
