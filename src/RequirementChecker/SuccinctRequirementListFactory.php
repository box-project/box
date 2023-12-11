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

namespace KevinGH\Box\RequirementChecker;

use KevinGH\Box\NotInstantiable;
use function iter\toArray;

/**
 * @private
 */
final class SuccinctRequirementListFactory
{
    use NotInstantiable;

    /**
     * Generates a succinct, i.e. a human-readable (still) list of
     * the requirements but excluding the sources.
     *
     * This format is more helpful when interested in the final
     * requirement list without caring about the details source as
     * the source of the requirement or remedies.
     *
     * @return list<string>
     */
    public static function create(Requirements $requirements): array
    {
        $succinctRequirements = array_unique(
            array_map(
                static fn (Requirement $requirement) => $requirement->toSuccinctDescription(),
                toArray($requirements),
            ),
        );

        ksort($succinctRequirements, SORT_STRING);

        return $succinctRequirements;
    }
}
