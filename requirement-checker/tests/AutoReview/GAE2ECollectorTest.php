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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use function count;

/**
 * @internal
 */
#[CoversClass(\KevinGH\Box\AutoReview\GAE2ECollector::class)]
class GAE2ECollectorTest extends TestCase
{
    public function test_it_collects_the_e2e_test_names(): void
    {
        $names = GAE2ECollector::getExecutedE2ETests();

        self::assertGreaterThan(0, count($names));

        foreach ($names as $name) {
            self::assertMatchesRegularExpression('/^_?test_e2e_[\p{L}_]+$/', $name);
        }
    }
}
