<?php

declare(strict_types=1);

namespace KevinGH\RequirementChecker;

final class ConditionIsNotFulfilled implements IsFulfilled
{
    public function __invoke(): bool
    {
        return false;
    }
}
