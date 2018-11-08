<?php

namespace _HumbugBoxacafcfe30294\Composer\Semver\Constraint;

interface ConstraintInterface
{
    public function matches(\_HumbugBoxacafcfe30294\Composer\Semver\Constraint\ConstraintInterface $provider);
    public function getPrettyString();
    public function __toString();
}
