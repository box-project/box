<?php

namespace _HumbugBoxaa731ba336da\Composer\Semver\Constraint;

interface ConstraintInterface
{
    public function matches(\_HumbugBoxaa731ba336da\Composer\Semver\Constraint\ConstraintInterface $provider);
    public function getPrettyString();
    public function __toString();
}
