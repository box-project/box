<?php

declare (strict_types=1);
namespace HumbugBox470\KevinGH\RequirementChecker;

interface IsFulfilled
{
    public function __invoke(): bool;
}
