<?php

namespace HumbugBox384\Composer\Semver\Constraint;

interface ConstraintInterface
{
    public function matches(\HumbugBox384\Composer\Semver\Constraint\ConstraintInterface $provider);
    public function getPrettyString();
    public function __toString();
}
