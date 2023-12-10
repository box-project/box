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

namespace KevinGH\RequirementChecker\AutoReview;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
final class GAE2ETest extends TestCase
{
    public function test_github_actions_executes_all_the_e2e_tests(): void
    {
        $e2eRule = MakefileE2ECollector::getE2ERule();
        $e2eRulePrerequisites = $e2eRule->getPrerequisites();
        array_shift($e2eRulePrerequisites);   // Remove docker-images

        $actual = GAE2ECollector::getExecutedE2ETests();

        self::assertEqualsCanonicalizing($e2eRulePrerequisites, $actual);
    }
}
