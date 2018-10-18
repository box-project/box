<?php

namespace _HumbugBoxc5a6d13bc633\Composer\Semver\Constraint;

interface ConstraintInterface
{
    /**
    @param
    @return
    */
    public function matches(\_HumbugBoxc5a6d13bc633\Composer\Semver\Constraint\ConstraintInterface $provider);
    /**
    @return
    */
    public function getPrettyString();
    /**
    @return
    */
    public function __toString();
}
