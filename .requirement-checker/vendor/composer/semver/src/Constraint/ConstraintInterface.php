<?php

namespace HumbugBox3100\Composer\Semver\Constraint;

interface ConstraintInterface
{
    public function matches(\HumbugBox3100\Composer\Semver\Constraint\ConstraintInterface $provider);
    public function compile($operator);
    public function getUpperBound();
    public function getLowerBound();
    public function getPrettyString();
    public function setPrettyString($prettyString);
    public function __toString();
}
