<?php

declare(strict_types=1);

namespace KevinGH\Box\Composer\Package;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;
use function count;

final class Extensions implements IteratorAggregate, Countable
{
    /**
     *@param Extension[] $extensions
     */
    public function __construct(
        public readonly array $extensions = []
    ) {
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->extensions);
    }

    public function count(): int
    {
        return count($this->extensions);
    }
}