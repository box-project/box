<?php

namespace _HumbugBoxbb220723f65b\Composer\Semver\Constraint;

interface ConstraintInterface
{
    public function matches(\_HumbugBoxbb220723f65b\Composer\Semver\Constraint\ConstraintInterface $provider);
    public function getPrettyString();
    public function __toString();
}
