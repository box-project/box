<?php

namespace HumbugBox384\Composer\Semver\Constraint;

class EmptyConstraint implements \HumbugBox384\Composer\Semver\Constraint\ConstraintInterface
{
    protected $prettyString;
    public function matches(\HumbugBox384\Composer\Semver\Constraint\ConstraintInterface $provider)
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
        return (string) $this;
    }
    public function __toString()
    {
        return '[]';
    }
}
