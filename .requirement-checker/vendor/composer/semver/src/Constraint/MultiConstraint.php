<?php

namespace _HumbugBox5af565a878e76\Composer\Semver\Constraint;

class MultiConstraint implements \_HumbugBox5af565a878e76\Composer\Semver\Constraint\ConstraintInterface
{
    /**
    @var */
    protected $constraints;
    /**
    @var */
    protected $prettyString;
    /**
    @var */
    protected $conjunctive;
    /**
    @param
    @param
    */
    public function __construct(array $constraints, $conjunctive = \true)
    {
        $this->constraints = $constraints;
        $this->conjunctive = $conjunctive;
    }
    /**
    @return
    */
    public function getConstraints()
    {
        return $this->constraints;
    }
    /**
    @return
    */
    public function isConjunctive()
    {
        return $this->conjunctive;
    }
    /**
    @return
    */
    public function isDisjunctive()
    {
        return !$this->conjunctive;
    }
    /**
    @param
    @return
    */
    public function matches(\_HumbugBox5af565a878e76\Composer\Semver\Constraint\ConstraintInterface $provider)
    {
        if (\false === $this->conjunctive) {
            foreach ($this->constraints as $constraint) {
                if ($constraint->matches($provider)) {
                    return \true;
                }
            }
            return \false;
        }
        foreach ($this->constraints as $constraint) {
            if (!$constraint->matches($provider)) {
                return \false;
            }
        }
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
        $constraints = array();
        foreach ($this->constraints as $constraint) {
            $constraints[] = (string) $constraint;
        }
        return '[' . \implode($this->conjunctive ? ' ' : ' || ', $constraints) . ']';
    }
}
