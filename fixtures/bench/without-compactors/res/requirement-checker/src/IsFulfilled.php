<?php

declare (strict_types=1);
namespace HumbugBox451\KevinGH\RequirementChecker;

/** @internal */
interface IsFulfilled
{
    public function __invoke() : bool;
}
