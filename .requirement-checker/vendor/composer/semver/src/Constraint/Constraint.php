<?php

namespace HumbugBox3100\Composer\Semver\Constraint;

class Constraint implements \HumbugBox3100\Composer\Semver\Constraint\ConstraintInterface
{
    const OP_EQ = 0;
    const OP_LT = 1;
    const OP_LE = 2;
    const OP_GT = 3;
    const OP_GE = 4;
    const OP_NE = 5;
    /**
    @phpstan-var
    */
    private static $transOpStr = array('=' => self::OP_EQ, '==' => self::OP_EQ, '<' => self::OP_LT, '<=' => self::OP_LE, '>' => self::OP_GT, '>=' => self::OP_GE, '<>' => self::OP_NE, '!=' => self::OP_NE);
    /**
    @phpstan-var
    */
    private static $transOpInt = array(self::OP_EQ => '==', self::OP_LT => '<', self::OP_LE => '<=', self::OP_GT => '>', self::OP_GE => '>=', self::OP_NE => '!=');
    /**
    @phpstan-var
    */
    protected $operator;
    protected $version;
    protected $prettyString;
    protected $lowerBound;
    protected $upperBound;
    public function __construct($operator, $version)
    {
        if (!isset(self::$transOpStr[$operator])) {
            throw new \InvalidArgumentException(\sprintf('Invalid operator "%s" given, expected one of: %s', $operator, \implode(', ', self::getSupportedOperators())));
        }
        $this->operator = self::$transOpStr[$operator];
        $this->version = $version;
    }
    public function getVersion()
    {
        return $this->version;
    }
    public function getOperator()
    {
        return self::$transOpInt[$this->operator];
    }
    public function matches(\HumbugBox3100\Composer\Semver\Constraint\ConstraintInterface $provider)
    {
        if ($provider instanceof self) {
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
    /**
    @phpstan-return
    */
    public static function getOperatorConstant($operator)
    {
        return self::$transOpStr[$operator];
    }
    public function versionCompare($a, $b, $operator, $compareBranches = \false)
    {
        if (!isset(self::$transOpStr[$operator])) {
            throw new \InvalidArgumentException(\sprintf('Invalid operator "%s" given, expected one of: %s', $operator, \implode(', ', self::getSupportedOperators())));
        }
        $aIsBranch = 'dev-' === \substr($a, 0, 4);
        $bIsBranch = 'dev-' === \substr($b, 0, 4);
        if ($operator === '!=' && ($aIsBranch || $bIsBranch)) {
            return $a !== $b;
        }
        if ($aIsBranch && $bIsBranch) {
            return $operator === '==' && $a === $b;
        }
        if (!$compareBranches && ($aIsBranch || $bIsBranch)) {
            return \false;
        }
        return \version_compare($a, $b, $operator);
    }
    public function compile($otherOperator)
    {
        if ($this->version[0] === 'd' && 'dev-' === \substr($this->version, 0, 4)) {
            if (self::OP_EQ === $this->operator) {
                if (self::OP_EQ === $otherOperator) {
                    return \sprintf('$b && $v === %s', \var_export($this->version, \true));
                }
                if (self::OP_NE === $otherOperator) {
                    return \sprintf('!$b || $v !== %s', \var_export($this->version, \true));
                }
                return 'false';
            }
            if (self::OP_NE === $this->operator) {
                if (self::OP_EQ === $otherOperator) {
                    return \sprintf('!$b || $v !== %s', \var_export($this->version, \true));
                }
                if (self::OP_NE === $otherOperator) {
                    return 'true';
                }
                return '!$b';
            }
            return 'false';
        }
        if (self::OP_EQ === $this->operator) {
            if (self::OP_EQ === $otherOperator) {
                return \sprintf('\\version_compare($v, %s, \'==\')', \var_export($this->version, \true));
            }
            if (self::OP_NE === $otherOperator) {
                return \sprintf('$b || \\version_compare($v, %s, \'!=\')', \var_export($this->version, \true));
            }
            return \sprintf('!$b && \\version_compare(%s, $v, \'%s\')', \var_export($this->version, \true), self::$transOpInt[$otherOperator]);
        }
        if (self::OP_NE === $this->operator) {
            if (self::OP_EQ === $otherOperator) {
                return \sprintf('$b || (!$b && \\version_compare($v, %s, \'!=\'))', \var_export($this->version, \true));
            }
            if (self::OP_NE === $otherOperator) {
                return 'true';
            }
            return '!$b';
        }
        if (self::OP_LT === $this->operator || self::OP_LE === $this->operator) {
            if (self::OP_LT === $otherOperator || self::OP_LE === $otherOperator) {
                return '!$b';
            }
        } elseif (self::OP_GT === $this->operator || self::OP_GE === $this->operator) {
            if (self::OP_GT === $otherOperator || self::OP_GE === $otherOperator) {
                return '!$b';
            }
        }
        if (self::OP_NE === $otherOperator) {
            return 'true';
        }
        $codeComparison = \sprintf('\\version_compare($v, %s, \'%s\')', \var_export($this->version, \true), self::$transOpInt[$this->operator]);
        if ($this->operator === self::OP_LE) {
            if ($otherOperator === self::OP_GT) {
                return \sprintf('!$b && \\version_compare($v, %s, \'!=\') && ', \var_export($this->version, \true)) . $codeComparison;
            }
        } elseif ($this->operator === self::OP_GE) {
            if ($otherOperator === self::OP_LT) {
                return \sprintf('!$b && \\version_compare($v, %s, \'!=\') && ', \var_export($this->version, \true)) . $codeComparison;
            }
        }
        return \sprintf('!$b && %s', $codeComparison);
    }
    public function matchSpecific(\HumbugBox3100\Composer\Semver\Constraint\Constraint $provider, $compareBranches = \false)
    {
        $noEqualOp = \str_replace('=', '', self::$transOpInt[$this->operator]);
        $providerNoEqualOp = \str_replace('=', '', self::$transOpInt[$provider->operator]);
        $isEqualOp = self::OP_EQ === $this->operator;
        $isNonEqualOp = self::OP_NE === $this->operator;
        $isProviderEqualOp = self::OP_EQ === $provider->operator;
        $isProviderNonEqualOp = self::OP_NE === $provider->operator;
        if ($isNonEqualOp || $isProviderNonEqualOp) {
            if ($isNonEqualOp && !$isProviderNonEqualOp && !$isProviderEqualOp && 'dev-' === \substr($provider->version, 0, 4)) {
                return \false;
            }
            if ($isProviderNonEqualOp && !$isNonEqualOp && !$isEqualOp && 'dev-' === \substr($this->version, 0, 4)) {
                return \false;
            }
            if (!$isEqualOp && !$isProviderEqualOp) {
                return \true;
            }
            return $this->versionCompare($provider->version, $this->version, '!=', $compareBranches);
        }
        if ($this->operator !== self::OP_EQ && $noEqualOp === $providerNoEqualOp) {
            if ('dev-' === \substr($this->version, 0, 4) || 'dev-' === \substr($provider->version, 0, 4)) {
                return \false;
            }
            return \true;
        }
        $version1 = $isEqualOp ? $this->version : $provider->version;
        $version2 = $isEqualOp ? $provider->version : $this->version;
        $operator = $isEqualOp ? $provider->operator : $this->operator;
        if ($this->versionCompare($version1, $version2, self::$transOpInt[$operator], $compareBranches)) {
            return !(self::$transOpInt[$provider->operator] === $providerNoEqualOp && self::$transOpInt[$this->operator] !== $noEqualOp && \version_compare($provider->version, $this->version, '=='));
        }
        return \false;
    }
    public function __toString()
    {
        return self::$transOpInt[$this->operator] . ' ' . $this->version;
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
    private function extractBounds()
    {
        if (null !== $this->lowerBound) {
            return;
        }
        if (\strpos($this->version, 'dev-') === 0) {
            $this->lowerBound = \HumbugBox3100\Composer\Semver\Constraint\Bound::zero();
            $this->upperBound = \HumbugBox3100\Composer\Semver\Constraint\Bound::positiveInfinity();
            return;
        }
        switch ($this->operator) {
            case self::OP_EQ:
                $this->lowerBound = new \HumbugBox3100\Composer\Semver\Constraint\Bound($this->version, \true);
                $this->upperBound = new \HumbugBox3100\Composer\Semver\Constraint\Bound($this->version, \true);
                break;
            case self::OP_LT:
                $this->lowerBound = \HumbugBox3100\Composer\Semver\Constraint\Bound::zero();
                $this->upperBound = new \HumbugBox3100\Composer\Semver\Constraint\Bound($this->version, \false);
                break;
            case self::OP_LE:
                $this->lowerBound = \HumbugBox3100\Composer\Semver\Constraint\Bound::zero();
                $this->upperBound = new \HumbugBox3100\Composer\Semver\Constraint\Bound($this->version, \true);
                break;
            case self::OP_GT:
                $this->lowerBound = new \HumbugBox3100\Composer\Semver\Constraint\Bound($this->version, \false);
                $this->upperBound = \HumbugBox3100\Composer\Semver\Constraint\Bound::positiveInfinity();
                break;
            case self::OP_GE:
                $this->lowerBound = new \HumbugBox3100\Composer\Semver\Constraint\Bound($this->version, \true);
                $this->upperBound = \HumbugBox3100\Composer\Semver\Constraint\Bound::positiveInfinity();
                break;
            case self::OP_NE:
                $this->lowerBound = \HumbugBox3100\Composer\Semver\Constraint\Bound::zero();
                $this->upperBound = \HumbugBox3100\Composer\Semver\Constraint\Bound::positiveInfinity();
                break;
        }
    }
}
