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

namespace HumbugBox451\KevinGH\RequirementChecker;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;
use function array_reduce;
use function count;
use function get_cfg_var;
use const true;

/** @internal */
final class RequirementCollection implements IteratorAggregate, Countable
{
    private $requirements = [];
    private $phpIniPath;

    public function __construct($phpIniPath = null)
    {
        $this->phpIniPath = null === $phpIniPath ? get_cfg_var('cfg_file_path') : $phpIniPath;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->requirements);
    }

    public function count(): int
    {
        return count($this->requirements);
    }

    public function add(Requirement $requirement): void
    {
        $this->requirements[] = $requirement;
    }

    public function addRequirement(IsFulfilled $checkIsFulfilled, string $testMessage, string $helpText): void
    {
        $this->add(new Requirement($checkIsFulfilled, $testMessage, $helpText));
    }

    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function getPhpIniPath()
    {
        return $this->phpIniPath;
    }

    public function evaluateRequirements()
    {
        return array_reduce($this->requirements, static fn (bool $checkPassed, Requirement $requirement): bool => $checkPassed && $requirement->isFulfilled(), true);
    }
}
