<?php

declare(strict_types=1);

namespace KevinGH\RequirementChecker;

final class ConditionIsFulfilled implements IsFulfilled
{
    public function __invoke(): bool
    {
        return true;
    }
}
