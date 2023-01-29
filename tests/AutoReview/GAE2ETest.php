<?php

declare(strict_types=1);

namespace KevinGH\Box\AutoReview;


use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
final class GAE2ETest extends TestCase
{
    public function test_github_actions_executes_all_the_e2e_tests(): void
    {
        $expected = MakefileE2ECollector::getE2ERule()->getPrerequisites();
        $actual = GAE2ECollector::getExecutedE2ETests();

        self::assertEqualsCanonicalizing($expected, $actual);
    }
}
