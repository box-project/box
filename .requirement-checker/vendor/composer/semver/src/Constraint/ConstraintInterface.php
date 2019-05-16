<?php

namespace _HumbugBox87c495005ea2\Composer\Semver\Constraint;

interface ConstraintInterface
{
    public function matches(\_HumbugBox87c495005ea2\Composer\Semver\Constraint\ConstraintInterface $provider);
    public function getPrettyString();
    public function __toString();
}
