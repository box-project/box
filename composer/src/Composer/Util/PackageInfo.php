<?php declare(strict_types=1);











namespace Composer\Util;

use Composer\Package\CompletePackageInterface;
use Composer\Package\PackageInterface;

class PackageInfo
{
public static function getViewSourceUrl(PackageInterface $package): ?string
{
if ($package instanceof CompletePackageInterface && isset($package->getSupport()['source'])) {
return $package->getSupport()['source'];
}

return $package->getSourceUrl();
}

public static function getViewSourceOrHomepageUrl(PackageInterface $package): ?string
{
return self::getViewSourceUrl($package) ?? ($package instanceof CompletePackageInterface ? $package->getHomepage() : null);
}
}
