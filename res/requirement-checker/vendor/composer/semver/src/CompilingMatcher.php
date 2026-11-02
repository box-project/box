<?php

namespace HumbugBox471\Composer\Semver;

use HumbugBox471\Composer\Semver\Constraint\Constraint;
use HumbugBox471\Composer\Semver\Constraint\ConstraintInterface;
class CompilingMatcher
{
    /**
    @phpstan-var
    */
    private static $compiledCheckerCache = array();
    /**
    @phpstan-var
    */
    private static $resultCache = array();
    private static $enabled;
    /**
    @phpstan-var
    */
    private static $transOpInt = array(Constraint::OP_EQ => Constraint::STR_OP_EQ, Constraint::OP_LT => Constraint::STR_OP_LT, Constraint::OP_LE => Constraint::STR_OP_LE, Constraint::OP_GT => Constraint::STR_OP_GT, Constraint::OP_GE => Constraint::STR_OP_GE, Constraint::OP_NE => Constraint::STR_OP_NE);
    public static function clear()
    {
        self::$resultCache = array();
        self::$compiledCheckerCache = array();
    }
    /**
    @phpstan-param
    */
    public static function match(ConstraintInterface $constraint, $operator, $version)
    {
        $constraintString = (string) $constraint;
        $resultCache =& self::$resultCache[$operator][$constraintString];
        if (isset($resultCache[$version])) {
            return $resultCache[$version];
        }
        if (self::$enabled === null) {
            self::$enabled = !\in_array('eval', explode(',', (string) ini_get('disable_functions')), \true);
        }
        if (!self::$enabled) {
            return $resultCache[$version] = $constraint->matches(new Constraint(self::$transOpInt[$operator], $version));
        }
        $compiledCheckerCache =& self::$compiledCheckerCache[$operator][$constraintString];
        if (null === $compiledCheckerCache) {
            $code = $constraint->compile($operator);
            $compiledCheckerCache = eval('return function($v, $b){return ' . $code . ';};');
        }
        return $resultCache[$version] = $compiledCheckerCache($version, strpos($version, 'dev-') === 0);
    }
}
