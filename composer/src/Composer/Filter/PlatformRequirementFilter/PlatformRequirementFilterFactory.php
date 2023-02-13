<?php declare(strict_types=1);











namespace Composer\Filter\PlatformRequirementFilter;

final class PlatformRequirementFilterFactory
{



public static function fromBoolOrList($boolOrList): PlatformRequirementFilterInterface
{
if (is_bool($boolOrList)) {
return $boolOrList ? self::ignoreAll() : self::ignoreNothing();
}

if (is_array($boolOrList)) {
return new IgnoreListPlatformRequirementFilter($boolOrList);
}

throw new \InvalidArgumentException(
sprintf(
'PlatformRequirementFilter: Unknown $boolOrList parameter %s. Please report at https://github.com/composer/composer/issues/new.',
gettype($boolOrList)
)
);
}

public static function ignoreAll(): PlatformRequirementFilterInterface
{
return new IgnoreAllPlatformRequirementFilter();
}

public static function ignoreNothing(): PlatformRequirementFilterInterface
{
return new IgnoreNothingPlatformRequirementFilter();
}
}
