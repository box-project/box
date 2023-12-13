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

use Fidry\Makefile\Rule;
use Fidry\Makefile\Test\BaseMakefileTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * @internal
 */
#[CoversNothing]
final class E2EMakefileTest extends BaseMakefileTestCase
{
    public const MAKEFILE_PATH = __DIR__.'/../../Makefile.e2e';

    protected static function getMakefilePath(): string
    {
        return self::MAKEFILE_PATH;
    }

    protected function getExpectedHelpOutput(): string
    {
        self::markTestSkipped('There is no help command.');
    }

    public function test_the_e2e_target_must_contain_all_the_e2e_targets(): void
    {
        $e2eRule = MakefileE2ECollector::getE2ERule();
        $e2eTestTargets = self::getE2ETestRules();

        $e2eRulePrerequisites = $e2eRule->getPrerequisites();

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

    /**
     * @return list<Rule>
     */
    private static function getE2ETestRules(): array
    {
        return array_values(
            array_filter(
                self::getParsedRules(),
                static fn (Rule $rule) => str_starts_with($rule->getTarget(), 'e2e_') && !$rule->isComment() && !$rule->isPhony(),
            ),
        );
    }
}
