<?php

namespace _HumbugBox87c495005ea2\Composer\Semver\Constraint;

class EmptyConstraint implements \_HumbugBox87c495005ea2\Composer\Semver\Constraint\ConstraintInterface
{
    protected $prettyString;
    public function matches(\_HumbugBox87c495005ea2\Composer\Semver\Constraint\ConstraintInterface $provider)
    {
        return \true;
    }
    public function setPrettyString($prettyString)
    {
        $this->prettyString = $prettyString;
    }
    public function getPrettyString()
    {
        if ($this->prettyString) {
            return $this->prettyString;
        }
        return $this->__toString();
    }
    public function __toString()
    {
        return '[]';
    }
}
