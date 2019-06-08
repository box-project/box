<?php

namespace HumbugBox373\Composer\Semver\Constraint;

class MultiConstraint implements \HumbugBox373\Composer\Semver\Constraint\ConstraintInterface
{
    protected $constraints;
    protected $prettyString;
    protected $conjunctive;
    public function __construct(array $constraints, $conjunctive = \true)
    {
        $this->constraints = $constraints;
        $this->conjunctive = $conjunctive;
    }
    public function getConstraints()
    {
        return $this->constraints;
    }
    public function isConjunctive()
    {
        return $this->conjunctive;
    }
    public function isDisjunctive()
    {
        return !$this->conjunctive;
    }
    public function matches(\HumbugBox373\Composer\Semver\Constraint\ConstraintInterface $provider)
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
        $constraints = array();
        foreach ($this->constraints as $constraint) {
            $constraints[] = (string) $constraint;
        }
        return '[' . \implode($this->conjunctive ? ' ' : ' || ', $constraints) . ']';
    }
}
