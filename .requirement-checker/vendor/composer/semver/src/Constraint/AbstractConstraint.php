<?php

namespace _HumbugBoxaa731ba336da\Composer\Semver\Constraint;

\trigger_error('The ' . __CLASS__ . ' abstract class is deprecated, there is no replacement for it, it will be removed in the next major version.', \E_USER_DEPRECATED);
abstract class AbstractConstraint implements \_HumbugBoxaa731ba336da\Composer\Semver\Constraint\ConstraintInterface
{
    protected $prettyString;
    public function matches(\_HumbugBoxaa731ba336da\Composer\Semver\Constraint\ConstraintInterface $provider)
    {
        if ($provider instanceof $this) {
            return $this->matchSpecific($provider);
        }
        return $provider->matches($this);
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
}
