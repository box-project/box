<?php

declare (strict_types=1);
namespace HumbugBox462\KevinGH\RequirementChecker;

interface IsFulfilled
{
    public function __invoke(): bool;
}
