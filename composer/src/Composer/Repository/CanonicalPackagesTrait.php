<?php declare(strict_types=1);











namespace Composer\Repository;

use Composer\Package\AliasPackage;
use Composer\Package\PackageInterface;






trait CanonicalPackagesTrait
{





public function getCanonicalPackages()
{
$packages = $this->getPackages();


$packagesByName = [];
foreach ($packages as $package) {
if (!isset($packagesByName[$package->getName()]) || $packagesByName[$package->getName()] instanceof AliasPackage) {
$packagesByName[$package->getName()] = $package;
}
}

$canonicalPackages = [];


foreach ($packagesByName as $package) {
while ($package instanceof AliasPackage) {
$package = $package->getAliasOf();
}

$canonicalPackages[] = $package;
}

return $canonicalPackages;
}
}
