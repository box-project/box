<?php

namespace HumbugBox451\Composer\Semver\Constraint;

/** @internal */
class MatchAllConstraint implements ConstraintInterface
{
    protected $prettyString;
    public function matches(ConstraintInterface $provider)
    {
        return \true;
    }
    public function compile($otherOperator)
    {
        return 'true';
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
        return '*';
    }
    public function getUpperBound()
    {
        return Bound::positiveInfinity();
    }
    public function getLowerBound()
    {
        return Bound::zero();
    }
}
