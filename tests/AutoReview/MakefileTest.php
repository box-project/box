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

namespace KevinGH\Box\AutoReview;

use Fidry\Makefile\Test\BaseMakefileTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * @internal
 */
#[CoversNothing]
final class MakefileTest extends BaseMakefileTestCase
{
    public const MAKEFILE_PATH = __DIR__.'/../../Makefile';

    protected static function getMakefilePath(): string
    {
        return self::MAKEFILE_PATH;
    }

    protected function getExpectedHelpOutput(): string
    {
        return <<<'EOF'
            [33mUsage:[0m
              make TARGET

            [32m#
            # Commands
            #---------------------------------------------------------------------------[0m

            [33mcheck:[0m			  Runs all the checks
            [33mclean:[0m 	 		  Cleans all created artifacts
            [33mcompile:[0m 		  Compiles the application into the PHAR
            [33mdump_requirement_checker:[0m Dumps the requirement checker
            [33mautoreview:[0m 		  AutoReview checks
            [33mcs:[0m	 		  Fixes CS
            [33mcs_lint:[0m 	 	  Lints CS
            [33mtest:[0m		  	  Runs all the tests
            [33mphpunit_coverage_html:[0m       Runs PHPUnit with code coverage with HTML report
            [33mphpunit_coverage_infection:[0m  Runs PHPUnit tests with test coverage
            [33mphpbench:[0m 		  Runs PHPBench
            [33mblackfire:[0m		  Profiles the compile step
            [33mwebsite_check:[0m		  Runs various checks for the website
            [33mwebsite_build:[0m		  Builds the website
            [33mwebsite_serve:[0m		  Serves the website locally

            EOF;
    }
}
