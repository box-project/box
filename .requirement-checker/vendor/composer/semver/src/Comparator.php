<?php

namespace _HumbugBoxbb220723f65b\Composer\Semver;

use _HumbugBoxbb220723f65b\Composer\Semver\Constraint\Constraint;
class Comparator
{
    public static function greaterThan($version1, $version2)
    {
        return self::compare($version1, '>', $version2);
    }
    public static function greaterThanOrEqualTo($version1, $version2)
    {
        return self::compare($version1, '>=', $version2);
    }
    public static function lessThan($version1, $version2)
    {
        return self::compare($version1, '<', $version2);
    }
    public static function lessThanOrEqualTo($version1, $version2)
    {
        return self::compare($version1, '<=', $version2);
    }
    public static function equalTo($version1, $version2)
    {
        return self::compare($version1, '==', $version2);
    }
    public static function notEqualTo($version1, $version2)
    {
        return self::compare($version1, '!=', $version2);
    }
    public static function compare($version1, $operator, $version2)
    {
        $constraint = new \_HumbugBoxbb220723f65b\Composer\Semver\Constraint\Constraint($operator, $version2);
        return $constraint->matches(new \_HumbugBoxbb220723f65b\Composer\Semver\Constraint\Constraint('==', $version1));
    }
}
