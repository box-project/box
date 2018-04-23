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

require __DIR__ . '/../vendor/autoload.php';
$checkPassed = \_HumbugBox5addf3ce683e7\KevinGH\RequirementChecker\Checker::checkRequirements();
if (\false === $checkPassed) {
    exit(1);
}
