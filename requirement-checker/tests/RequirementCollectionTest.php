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

namespace KevinGH\RequirementChecker;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use function iterator_to_array;

/**
 * @internal
 */
#[CoversClass(RequirementCollection::class)]
class RequirementCollectionTest extends TestCase
{
    public function test_it_is_empty_by_default(): void
    {
        $requirements = new RequirementCollection();

        self::assertSame([], iterator_to_array($requirements, false));
        self::assertSame([], $requirements->getRequirements());
        self::assertCount(0, $requirements);
        self::assertTrue($requirements->evaluateRequirements());
    }

    public function test_it_can_have_and_evaluate_requirements(): void
    {
        $requirements = new RequirementCollection();

        $reqs = [
            $requirementA = new Requirement(
                new ConditionIsFulfilled(),
                'req tA',
                'req hA'
            ),
            $requirementB = new Requirement(
                new ConditionIsFulfilled(),
                'req tB',
                'req hB'
            ),
        ];

        foreach ($reqs as $requirement) {
            $requirements->add($requirement);
        }

        self::assertSame($reqs, iterator_to_array($requirements, false));
        self::assertSame($reqs, $requirements->getRequirements());
        self::assertCount(2, $requirements);
        self::assertTrue($requirements->evaluateRequirements());

        $requirements->addRequirement(
            $check = new ConditionIsNotFulfilled(),
            'req tC',
            'req hC'
        );

        self::assertCount(3, $requirements);
        self::assertFalse($requirements->evaluateRequirements());

        $retrievedRequirements = $requirements->getRequirements();

        self::assertSame($retrievedRequirements, iterator_to_array($requirements, false));

        self::assertSame($requirementA, $retrievedRequirements[0]);
        self::assertSame($requirementB, $retrievedRequirements[1]);

        $requirementC = $retrievedRequirements[2];

        self::assertSame($check, $requirementC->getIsFullfilledChecker());
        self::assertFalse($requirementC->isFulfilled());
        self::assertSame('req tC', $requirementC->getTestMessage());
        self::assertSame('req hC', $requirementC->getHelpText());
    }
}
