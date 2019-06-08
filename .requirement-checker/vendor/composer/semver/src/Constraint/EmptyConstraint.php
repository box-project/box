<?php

namespace HumbugBox373\Composer\Semver\Constraint;

class EmptyConstraint implements \HumbugBox373\Composer\Semver\Constraint\ConstraintInterface
{
    protected $prettyString;
    public function matches(\HumbugBox373\Composer\Semver\Constraint\ConstraintInterface $provider)
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
