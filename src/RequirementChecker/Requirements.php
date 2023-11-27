<?php

declare(strict_types=1);

namespace KevinGH\Box\RequirementChecker;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;
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
}