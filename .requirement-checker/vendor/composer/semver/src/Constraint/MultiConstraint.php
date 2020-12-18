<?php

namespace HumbugBox3100\Composer\Semver\Constraint;

class MultiConstraint implements \HumbugBox3100\Composer\Semver\Constraint\ConstraintInterface
{
    protected $constraints;
    protected $prettyString;
    protected $string;
    protected $conjunctive;
    protected $lowerBound;
    protected $upperBound;
    public function __construct(array $constraints, $conjunctive = \true)
    {
        if (\count($constraints) < 2) {
            throw new \InvalidArgumentException('Must provide at least two constraints for a MultiConstraint. Use ' . 'the regular Constraint class for one constraint only or MatchAllConstraint for none. You may use ' . 'MultiConstraint::create() which optimizes and handles those cases automatically.');
        }
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
    public function compile($otherOperator)
    {
        $parts = array();
        foreach ($this->constraints as $constraint) {
            $code = $constraint->compile($otherOperator);
            if ($code === 'true') {
                if (!$this->conjunctive) {
                    return 'true';
                }
            } elseif ($code === 'false') {
                if ($this->conjunctive) {
                    return 'false';
                }
            } else {
                $parts[] = '(' . $code . ')';
            }
        }
        if (!$parts) {
            return $this->conjunctive ? 'true' : 'false';
        }
        return $this->conjunctive ? \implode('&&', $parts) : \implode('||', $parts);
    }
    public function matches(\HumbugBox3100\Composer\Semver\Constraint\ConstraintInterface $provider)
    {
        if (\false === $this->conjunctive) {
            foreach ($this->constraints as $constraint) {
                if ($provider->matches($constraint)) {
                    return \true;
                }
            }
            return \false;
        }
        foreach ($this->constraints as $constraint) {
            if (!$provider->matches($constraint)) {
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
        return (string) $this;
    }
    public function __toString()
    {
        if ($this->string !== null) {
            return $this->string;
        }
        $constraints = array();
        foreach ($this->constraints as $constraint) {
            $constraints[] = (string) $constraint;
        }
        return $this->string = '[' . \implode($this->conjunctive ? ' ' : ' || ', $constraints) . ']';
    }
    public function getLowerBound()
    {
        $this->extractBounds();
        return $this->lowerBound;
    }
    public function getUpperBound()
    {
        $this->extractBounds();
        return $this->upperBound;
    }
    public static function create(array $constraints, $conjunctive = \true)
    {
        if (0 === \count($constraints)) {
            return new \HumbugBox3100\Composer\Semver\Constraint\MatchAllConstraint();
        }
        if (1 === \count($constraints)) {
            return $constraints[0];
        }
        $optimized = self::optimizeConstraints($constraints, $conjunctive);
        if ($optimized !== null) {
            list($constraints, $conjunctive) = $optimized;
            if (\count($constraints) === 1) {
                return $constraints[0];
            }
        }
        return new self($constraints, $conjunctive);
    }
    private static function optimizeConstraints(array $constraints, $conjunctive)
    {
        if (!$conjunctive) {
            $left = $constraints[0];
            $mergedConstraints = array();
            $optimized = \false;
            for ($i = 1, $l = \count($constraints); $i < $l; $i++) {
                $right = $constraints[$i];
                if ($left instanceof \HumbugBox3100\Composer\Semver\Constraint\MultiConstraint && $left->conjunctive && $right instanceof \HumbugBox3100\Composer\Semver\Constraint\MultiConstraint && $right->conjunctive && ($left0 = (string) $left->constraints[0]) && $left0[0] === '>' && $left0[1] === '=' && ($left1 = (string) $left->constraints[1]) && $left1[0] === '<' && ($right0 = (string) $right->constraints[0]) && $right0[0] === '>' && $right0[1] === '=' && ($right1 = (string) $right->constraints[1]) && $right1[0] === '<' && \substr($left1, 2) === \substr($right0, 3)) {
                    $optimized = \true;
                    $left = new \HumbugBox3100\Composer\Semver\Constraint\MultiConstraint(\array_merge(array($left->constraints[0], $right->constraints[1]), \array_slice($left->constraints, 2), \array_slice($right->constraints, 2)), \true);
                } else {
                    $mergedConstraints[] = $left;
                    $left = $right;
                }
            }
            if ($optimized) {
                $mergedConstraints[] = $left;
                return array($mergedConstraints, \false);
            }
        }
        return null;
    }
    private function extractBounds()
    {
        if (null !== $this->lowerBound) {
            return;
        }
        foreach ($this->constraints as $constraint) {
            if (null === $this->lowerBound && null === $this->upperBound) {
                $this->lowerBound = $constraint->getLowerBound();
                $this->upperBound = $constraint->getUpperBound();
                continue;
            }
            if ($constraint->getLowerBound()->compareTo($this->lowerBound, $this->isConjunctive() ? '>' : '<')) {
                $this->lowerBound = $constraint->getLowerBound();
            }
            if ($constraint->getUpperBound()->compareTo($this->upperBound, $this->isConjunctive() ? '<' : '>')) {
                $this->upperBound = $constraint->getUpperBound();
            }
        }
    }
}
