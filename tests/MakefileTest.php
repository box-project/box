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

namespace KevinGH\Box;

use Fidry\Makefile\Test\BaseMakefileTestCase;

/**
 * @coversNothing
 */
final class MakefileTest extends BaseMakefileTestCase
{
    protected static function getMakefilePath(): string
    {
        return __DIR__.'/../Makefile';
    }

    protected function getExpectedHelpOutput(): string
    {
        return <<<'EOF'
            [33mUsage:[0m
              make TARGET

            [32m#
            # Commands
            #---------------------------------------------------------------------------[0m

            [33mclean:[0m 	 		  Cleans all created artifacts
            [33mcs:[0m	  Fixes CS
            [33mcs_lint:[0m  Checks CS
            [33mcompile:[0m 		  Compiles the application into the PHAR
            [33mdump_requirement_checker:[0m Dumps the requirement checker
            [33mtest:[0m		  	  Runs all the tests
            [33mtu:[0m			  Runs the unit tests
            [33mtu_box:[0m			  Runs the unit tests
            [33mtu_box_phar_readonly:[0m 	  Runs the unit tests with the setting `phar.readonly` to `On`
            [33mtu_requirement_checker:[0m	  Runs the unit tests
            [33mtc:[0m			  Runs the unit tests with code coverage
            [33mtm:[0m			  Runs Infection
            [33me2e:[0m			  Runs all the end-to-end tests
            [33me2e_scoper_alias:[0m 	  Runs the end-to-end tests to check that the PHP-Scoper config API regarding the prefix alias is working
            [33me2e_scoper_expose_symbols:[0m 	  Runs the end-to-end tests to check that the PHP-Scoper config API regarding the symbols exposure is working
            [33me2e_check_requirements:[0m	  Runs the end-to-end tests for the check requirements feature
            [33me2e_php_settings_checker:[0m  Runs the end-to-end tests for the PHP settings handler
            [33me2e_symfony:[0m		  Packages a fresh Symfony app
            [33me2e_composer_installed_versions:[0m		  Packages an app using Composer\InstalledVersions
            [33me2e_phpstorm_stubs:[0m		  Project using symbols which should be vetted by PhpStormStubs
            [33mblackfire:[0m		  Profiles the compile step

            EOF;
    }
}
