<?php

namespace _HumbugBoxc5a6d13bc633\Composer\Semver;

use _HumbugBoxc5a6d13bc633\Composer\Semver\Constraint\Constraint;
class Comparator
{
    /**
    @param
    @param
    @return
    */
    public static function greaterThan($version1, $version2)
    {
        return self::compare($version1, '>', $version2);
    }
    /**
    @param
    @param
    @return
    */
    public static function greaterThanOrEqualTo($version1, $version2)
    {
        return self::compare($version1, '>=', $version2);
    }
    /**
    @param
    @param
    @return
    */
    public static function lessThan($version1, $version2)
    {
        return self::compare($version1, '<', $version2);
    }
    /**
    @param
    @param
    @return
    */
    public static function lessThanOrEqualTo($version1, $version2)
    {
        return self::compare($version1, '<=', $version2);
    }
    /**
    @param
    @param
    @return
    */
    public static function equalTo($version1, $version2)
    {
        return self::compare($version1, '==', $version2);
    }
    /**
    @param
    @param
    @return
    */
    public static function notEqualTo($version1, $version2)
    {
        return self::compare($version1, '!=', $version2);
    }
    /**
    @param
    @param
    @param
    @return
    */
    public static function compare($version1, $operator, $version2)
    {
        $constraint = new \_HumbugBoxc5a6d13bc633\Composer\Semver\Constraint\Constraint($operator, $version2);
        return $constraint->matches(new \_HumbugBoxc5a6d13bc633\Composer\Semver\Constraint\Constraint('==', $version1));
    }
}
