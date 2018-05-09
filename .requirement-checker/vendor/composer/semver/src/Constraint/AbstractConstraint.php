<?php

namespace _HumbugBox5af37057079ab\Composer\Semver\Constraint;

\trigger_error('The ' . __CLASS__ . ' abstract class is deprecated, there is no replacement for it, it will be removed in the next major version.', \E_USER_DEPRECATED);
abstract class AbstractConstraint implements \_HumbugBox5af37057079ab\Composer\Semver\Constraint\ConstraintInterface
{
    /**
    @var */
    protected $prettyString;
    /**
    @param
    @return
    */
    public function matches(\_HumbugBox5af37057079ab\Composer\Semver\Constraint\ConstraintInterface $provider)
    {
        if ($provider instanceof $this) {
            return $this->matchSpecific($provider);
        }
        return $provider->matches($this);
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
}
