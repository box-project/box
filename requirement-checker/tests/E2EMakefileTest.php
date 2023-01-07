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

use Fidry\Makefile\Parser;
use Fidry\Makefile\Rule;
use Fidry\Makefile\Test\BaseMakefileTestCase;
use function Safe\file_get_contents;

/**
 * @coversNothing
 *
 * @internal
 */
final class E2EMakefileTest extends BaseMakefileTestCase
{
    protected static function getMakefilePath(): string
    {
        return __DIR__.'/../Makefile.e2e';
    }

    protected function getExpectedHelpOutput(): string
    {
        return <<<'EOF'
            make[1]: *** No rule to make target `help'.  Stop.

            EOF;
    }

    public function test_the_e2e_target_must_contain_all_the_e2e_targets(): void
    {
        $e2eRule = self::getE2ERule();
        $e2eTestTargets = self::getE2ETestRules();

        $e2eRulePrerequisites = $e2eRule->getPrerequisites();
        array_shift($e2eRulePrerequisites);   // Remove docker-images

        // Sanity check
        self::assertGreaterThan(0, count($e2eRule->getPrerequisites()));

        self::assertEqualsCanonicalizing(
            $e2eRulePrerequisites,
            array_map(
                static fn (Rule $rule) => $rule->getTarget(),
                $e2eTestTargets,
            ),
        );
    }

    private static function getE2ERule(): Rule
    {
        return current(
            array_filter(
                Parser::parse(
                    file_get_contents(__DIR__.'/../Makefile'),
                ),
                static fn (Rule $rule) => 'test_e2e' === $rule->getTarget() && !$rule->isComment() && !$rule->isPhony(),
            ),
        );
    }

    /**
     * @return Rule
     */
    private static function getE2ETestRules(): array
    {
        return array_values(
            array_filter(
                self::getParsedRules(),
                static fn (Rule $rule) => str_starts_with($rule->getTarget(), '_test_e2e_') && !$rule->isComment() && !$rule->isPhony(),
            ),
        );
    }
}
