<?php

namespace _HumbugBoxd1e70270db87\Composer\Semver\Constraint;

class EmptyConstraint implements \_HumbugBoxd1e70270db87\Composer\Semver\Constraint\ConstraintInterface
{
    /**
    @var */
    protected $prettyString;
    /**
    @param
    @return
    */
    public function matches(\_HumbugBoxd1e70270db87\Composer\Semver\Constraint\ConstraintInterface $provider)
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
