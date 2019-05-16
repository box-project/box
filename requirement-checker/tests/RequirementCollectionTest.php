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

use PHPUnit\Framework\TestCase;
use function iterator_to_array;

/**
 * @covers \KevinGH\RequirementChecker\RequirementCollection
 */
class RequirementCollectionTest extends TestCase
{
    public function test_it_is_empty_by_default(): void
    {
        $requirements = new RequirementCollection();

        $this->assertSame([], iterator_to_array($requirements, false));
        $this->assertSame([], $requirements->getRequirements());
        $this->assertCount(0, $requirements);
        $this->assertTrue($requirements->evaluateRequirements());
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

        $this->assertSame($reqs, iterator_to_array($requirements, false));
        $this->assertSame($reqs, $requirements->getRequirements());
        $this->assertCount(2, $requirements);
        $this->assertTrue($requirements->evaluateRequirements());

        $requirements->addRequirement(
            $check = new ConditionIsNotFulfilled(),
            'req tC',
            'req hC'
        );

        $this->assertCount(3, $requirements);
        $this->assertFalse($requirements->evaluateRequirements());

        $retrievedRequirements = $requirements->getRequirements();

        $this->assertSame($retrievedRequirements, iterator_to_array($requirements, false));

        $this->assertSame($requirementA, $retrievedRequirements[0]);
        $this->assertSame($requirementB, $retrievedRequirements[1]);

        $requirementC = $retrievedRequirements[2];

        $this->assertSame($check, $requirementC->getIsFullfilledChecker());
        $this->assertFalse($requirementC->isFulfilled());
        $this->assertSame('req tC', $requirementC->getTestMessage());
        $this->assertSame('req hC', $requirementC->getHelpText());
    }
}
