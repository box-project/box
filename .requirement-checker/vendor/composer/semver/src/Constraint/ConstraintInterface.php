<?php

namespace _HumbugBox5af562ac6f36a\Composer\Semver\Constraint;

interface ConstraintInterface
{
    /**
    @param
    @return
    */
    public function matches(\_HumbugBox5af562ac6f36a\Composer\Semver\Constraint\ConstraintInterface $provider);
    /**
    @return
    */
    public function getPrettyString();
    /**
    @return
    */
    public function __toString();
}
