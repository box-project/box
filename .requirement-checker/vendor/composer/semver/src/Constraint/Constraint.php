<?php

namespace _HumbugBoxaa731ba336da\Composer\Semver\Constraint;

class Constraint implements \_HumbugBoxaa731ba336da\Composer\Semver\Constraint\ConstraintInterface
{
    const OP_EQ = 0;
    const OP_LT = 1;
    const OP_LE = 2;
    const OP_GT = 3;
    const OP_GE = 4;
    const OP_NE = 5;
    private static $transOpStr = array('=' => self::OP_EQ, '==' => self::OP_EQ, '<' => self::OP_LT, '<=' => self::OP_LE, '>' => self::OP_GT, '>=' => self::OP_GE, '<>' => self::OP_NE, '!=' => self::OP_NE);
    private static $transOpInt = array(self::OP_EQ => '==', self::OP_LT => '<', self::OP_LE => '<=', self::OP_GT => '>', self::OP_GE => '>=', self::OP_NE => '!=');
    protected $operator;
    protected $version;
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
    public static function getSupportedOperators()
    {
        return \array_keys(self::$transOpStr);
    }
    public function __construct($operator, $version)
    {
        if (!isset(self::$transOpStr[$operator])) {
            throw new \InvalidArgumentException(\sprintf('Invalid operator "%s" given, expected one of: %s', $operator, \implode(', ', self::getSupportedOperators())));
        }
        $this->operator = self::$transOpStr[$operator];
        $this->version = $version;
    }
    public function versionCompare($a, $b, $operator, $compareBranches = \false)
    {
        if (!isset(self::$transOpStr[$operator])) {
            throw new \InvalidArgumentException(\sprintf('Invalid operator "%s" given, expected one of: %s', $operator, \implode(', ', self::getSupportedOperators())));
        }
        $aIsBranch = 'dev-' === \substr($a, 0, 4);
        $bIsBranch = 'dev-' === \substr($b, 0, 4);
        if ($aIsBranch && $bIsBranch) {
            return $operator === '==' && $a === $b;
        }
        if (!$compareBranches && ($aIsBranch || $bIsBranch)) {
            return \false;
        }
        return \version_compare($a, $b, $operator);
    }
    public function matchSpecific(\_HumbugBoxaa731ba336da\Composer\Semver\Constraint\Constraint $provider, $compareBranches = \false)
    {
        $noEqualOp = \str_replace('=', '', self::$transOpInt[$this->operator]);
        $providerNoEqualOp = \str_replace('=', '', self::$transOpInt[$provider->operator]);
        $isEqualOp = self::OP_EQ === $this->operator;
        $isNonEqualOp = self::OP_NE === $this->operator;
        $isProviderEqualOp = self::OP_EQ === $provider->operator;
        $isProviderNonEqualOp = self::OP_NE === $provider->operator;
        if ($isNonEqualOp || $isProviderNonEqualOp) {
            return !$isEqualOp && !$isProviderEqualOp || $this->versionCompare($provider->version, $this->version, '!=', $compareBranches);
        }
        if ($this->operator !== self::OP_EQ && $noEqualOp === $providerNoEqualOp) {
            return \true;
        }
        if ($this->versionCompare($provider->version, $this->version, self::$transOpInt[$this->operator], $compareBranches)) {
            if ($provider->version === $this->version && self::$transOpInt[$provider->operator] === $providerNoEqualOp && self::$transOpInt[$this->operator] !== $noEqualOp) {
                return \false;
            }
            return \true;
        }
        return \false;
    }
    public function __toString()
    {
        return self::$transOpInt[$this->operator] . ' ' . $this->version;
    }
}
