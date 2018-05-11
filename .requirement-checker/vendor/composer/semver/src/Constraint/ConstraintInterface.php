<?php

namespace _HumbugBox5af55af77d4cf\Composer\Semver\Constraint;

interface ConstraintInterface
{
    /**
    @param
    @return
    */
    public function matches(\_HumbugBox5af55af77d4cf\Composer\Semver\Constraint\ConstraintInterface $provider);
    /**
    @return
    */
    public function getPrettyString();
    /**
    @return
    */
    public function __toString();
}
