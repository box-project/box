<?php

declare (strict_types=1);
namespace HumbugBox440\KevinGH\RequirementChecker;

interface IsFulfilled
{
    public function __invoke() : bool;
}
