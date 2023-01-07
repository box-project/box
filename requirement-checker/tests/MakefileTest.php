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

use Fidry\Makefile\Rule;
use Fidry\Makefile\Test\BaseMakefileTestCase;

/**
 * @coversNothing
 *
 * @internal
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

            [33mcheck:[0m	  Runs all checks
            [33mclean:[0m 	  Cleans all created artifacts
            [33mdump:[0m	  Dumps the requirement-checker
            [33mcs:[0m	  Fixes CS
            [33mcs_lint:[0m  Checks CS
            [33mtest:[0m	  Runs all the tests

            EOF;
    }

    public function test_the_test_rule_contains_all_test_targets(): void
    {
        $testRule = self::getTestRule();
        $testTargets = self::getTestRules();

        // Sanity check
        self::assertGreaterThan(0, count($testRule->getPrerequisites()));

        self::assertEqualsCanonicalizing(
            $testRule->getPrerequisites(),
            array_map(
                static fn (Rule $rule) => $rule->getTarget(),
                $testTargets,
            ),
        );
    }

    private static function getTestRule(): Rule
    {
        return current(
            array_filter(
                self::getParsedRules(),
                static fn (Rule $rule) => 'test' === $rule->getTarget() && !$rule->isComment() && !$rule->isPhony(),
            ),
        );
    }

    /**
     * @return list<Rule>
     */
    private static function getTestRules(): array
    {
        return array_values(
            array_filter(
                self::getParsedRules(),
                static fn (Rule $rule) => str_starts_with($rule->getTarget(), 'test_') && !$rule->isComment() && !$rule->isPhony(),
            ),
        );
    }
}
