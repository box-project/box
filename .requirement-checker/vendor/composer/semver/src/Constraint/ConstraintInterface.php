<?php

namespace _HumbugBox90eb36759167\Composer\Semver\Constraint;

interface ConstraintInterface
{
    public function matches(\_HumbugBox90eb36759167\Composer\Semver\Constraint\ConstraintInterface $provider);
    public function getPrettyString();
    public function __toString();
}
