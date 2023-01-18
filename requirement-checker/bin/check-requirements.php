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

// See https://github.com/easysoft/phpmicro for the micro SAPI.
if (false === in_array(PHP_SAPI, array('cli', 'phpdbg', 'embed', 'micro'), true)) {
    echo PHP_EOL.'The application may only be invoked from a command line, got "'.PHP_SAPI.'"'.PHP_EOL;

    exit(1);
}

if ((
        false === isset($_SERVER['BOX_REQUIREMENT_CHECKER'])
        || true === (bool) $_SERVER['BOX_REQUIREMENT_CHECKER']
    )
    && false === Checker::checkRequirements()
) {
    exit(1);
}
