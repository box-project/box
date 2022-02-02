<?php

namespace HumbugBox3141\Composer\Semver\Constraint;

interface ConstraintInterface
{
    public function matches(ConstraintInterface $provider);
    public function compile($operator);
    public function getUpperBound();
    public function getLowerBound();
    public function getPrettyString();
    public function setPrettyString($prettyString);
    public function __toString();
}
