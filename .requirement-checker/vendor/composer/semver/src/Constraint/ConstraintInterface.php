<?php

namespace _HumbugBox5af2be5c4ef55\Composer\Semver\Constraint;

interface ConstraintInterface
{
    /**
    @param
    @return
    */
    public function matches(\_HumbugBox5af2be5c4ef55\Composer\Semver\Constraint\ConstraintInterface $provider);
    /**
    @return
    */
    public function getPrettyString();
    /**
    @return
    */
    public function __toString();
}
