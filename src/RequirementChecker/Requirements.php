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

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;
use function array_map;
use function array_values;
use function count;

final readonly class Requirements implements Countable, IteratorAggregate
{
    /**
     * @param Requirement[] $requirements
     */
    public function __construct(private array $requirements)
    {
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->requirements);
    }

    public function count(): int
    {
        return count($this->requirements);
    }

    public function toArray(): array
    {
        return array_values(
            array_map(
                static fn (Requirement $requirement) => $requirement->toArray(),
                $this->requirements,
            ),
        );
    }
}
