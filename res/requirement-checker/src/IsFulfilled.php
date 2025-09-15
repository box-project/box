<?php

declare (strict_types=1);
namespace HumbugBox467\KevinGH\RequirementChecker;

interface IsFulfilled
{
    public function __invoke(): bool;
}
