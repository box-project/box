<?php

namespace _HumbugBox5af562ac6f36a\Composer\Semver\Constraint;

class EmptyConstraint implements \_HumbugBox5af562ac6f36a\Composer\Semver\Constraint\ConstraintInterface
{
    /**
    @var */
    protected $prettyString;
    /**
    @param
    @return
    */
    public function matches(\_HumbugBox5af562ac6f36a\Composer\Semver\Constraint\ConstraintInterface $provider)
    {
        return \true;
    }
    /**
    @param
    */
    public function setPrettyString($prettyString)
    {
        $this->prettyString = $prettyString;
    }
    /**
    @return
    */
    public function getPrettyString()
    {
        if ($this->prettyString) {
            return $this->prettyString;
        }
        return $this->__toString();
    }
    /**
    @return
    */
    public function __toString()
    {
        return '[]';
    }
}
