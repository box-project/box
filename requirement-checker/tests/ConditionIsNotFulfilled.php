<?php

declare(strict_types=1);

namespace KevinGH\RequirementChecker;

final class ConditionIsNotFulfilled implements IsFulfilled
{
    /**
     * {@inheritdoc}
     */
    public function __invoke(): bool
    {
        return false;
    }
}