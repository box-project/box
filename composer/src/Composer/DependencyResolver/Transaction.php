<?php declare(strict_types=1);











namespace Composer\DependencyResolver;

use Composer\Package\AliasPackage;
use Composer\Package\Link;
use Composer\Package\PackageInterface;
use Composer\Repository\PlatformRepository;
use Composer\DependencyResolver\Operation\OperationInterface;





class Transaction
{



protected $operations;





protected $presentPackages;





protected $resultPackageMap;




protected $resultPackagesByName = [];





public function __construct(array $presentPackages, array $resultPackages)
{
$this->presentPackages = $presentPackages;
$this->setResultPackageMaps($resultPackages);
$this->operations = $this->calculateOperations();
}




public function getOperations(): array
{
return $this->operations;
}




private function setResultPackageMaps(array $resultPackages): void
{
$packageSort = static function (PackageInterface $a, PackageInterface $b): int {

if ($a->getName() === $b->getName()) {
if ($a instanceof AliasPackage !== $b instanceof AliasPackage) {
return $a instanceof AliasPackage ? -1 : 1;
}

return strcmp($b->getVersion(), $a->getVersion());
}

return strcmp($b->getName(), $a->getName());
};

$this->resultPackageMap = [];
foreach ($resultPackages as $package) {
$this->resultPackageMap[spl_object_hash($package)] = $package;
foreach ($package->getNames() as $name) {
$this->resultPackagesByName[$name][] = $package;
}
}

uasort($this->resultPackageMap, $packageSort);
foreach ($this->resultPackagesByName as $name => $packages) {
uasort($this->resultPackagesByName[$name], $packageSort);
}
}




protected function calculateOperations(): array
{
$operations = [];

$presentPackageMap = [];
$removeMap = [];
$presentAliasMap = [];
$removeAliasMap = [];
foreach ($this->presentPackages as $package) {
if ($package instanceof AliasPackage) {
$presentAliasMap[$package->getName().'::'.$package->getVersion()] = $package;
$removeAliasMap[$package->getName().'::'.$package->getVersion()] = $package;
} else {
$presentPackageMap[$package->getName()] = $package;
$removeMap[$package->getName()] = $package;
}
}

$stack = $this->getRootPackages();

$visited = [];
$processed = [];

while (!empty($stack)) {
$package = array_pop($stack);

if (isset($processed[spl_object_hash($package)])) {
continue;
}

if (!isset($visited[spl_object_hash($package)])) {
$visited[spl_object_hash($package)] = true;

$stack[] = $package;
if ($package instanceof AliasPackage) {
$stack[] = $package->getAliasOf();
} else {
foreach ($package->getRequires() as $link) {
$possibleRequires = $this->getProvidersInResult($link);

foreach ($possibleRequires as $require) {
$stack[] = $require;
}
}
}
} elseif (!isset($processed[spl_object_hash($package)])) {
$processed[spl_object_hash($package)] = true;

if ($package instanceof AliasPackage) {
$aliasKey = $package->getName().'::'.$package->getVersion();
if (isset($presentAliasMap[$aliasKey])) {
unset($removeAliasMap[$aliasKey]);
} else {
$operations[] = new Operation\MarkAliasInstalledOperation($package);
}
} else {
if (isset($presentPackageMap[$package->getName()])) {
$source = $presentPackageMap[$package->getName()];



if ($package->getVersion() !== $presentPackageMap[$package->getName()]->getVersion() ||
$package->getDistReference() !== $presentPackageMap[$package->getName()]->getDistReference() ||
$package->getSourceReference() !== $presentPackageMap[$package->getName()]->getSourceReference()
) {
$operations[] = new Operation\UpdateOperation($source, $package);
}
unset($removeMap[$package->getName()]);
} else {
$operations[] = new Operation\InstallOperation($package);
unset($removeMap[$package->getName()]);
}
}
}
}

foreach ($removeMap as $name => $package) {
array_unshift($operations, new Operation\UninstallOperation($package));
}
foreach ($removeAliasMap as $nameVersion => $package) {
$operations[] = new Operation\MarkAliasUninstalledOperation($package);
}

$operations = $this->movePluginsToFront($operations);


$operations = $this->moveUninstallsToFront($operations);



















return $this->operations = $operations;
}









protected function getRootPackages(): array
{
$roots = $this->resultPackageMap;

foreach ($this->resultPackageMap as $packageHash => $package) {
if (!isset($roots[$packageHash])) {
continue;
}

foreach ($package->getRequires() as $link) {
$possibleRequires = $this->getProvidersInResult($link);

foreach ($possibleRequires as $require) {
if ($require !== $package) {
unset($roots[spl_object_hash($require)]);
}
}
}
}

return $roots;
}




protected function getProvidersInResult(Link $link): array
{
if (!isset($this->resultPackagesByName[$link->getTarget()])) {
return [];
}

return $this->resultPackagesByName[$link->getTarget()];
}














private function movePluginsToFront(array $operations): array
{
$dlModifyingPluginsNoDeps = [];
$dlModifyingPluginsWithDeps = [];
$dlModifyingPluginRequires = [];
$pluginsNoDeps = [];
$pluginsWithDeps = [];
$pluginRequires = [];

foreach (array_reverse($operations, true) as $idx => $op) {
if ($op instanceof Operation\InstallOperation) {
$package = $op->getPackage();
} elseif ($op instanceof Operation\UpdateOperation) {
$package = $op->getTargetPackage();
} else {
continue;
}

$isDownloadsModifyingPlugin = $package->getType() === 'composer-plugin' && ($extra = $package->getExtra()) && isset($extra['plugin-modifies-downloads']) && $extra['plugin-modifies-downloads'] === true;


if ($isDownloadsModifyingPlugin || count(array_intersect($package->getNames(), $dlModifyingPluginRequires))) {

$requires = array_filter(array_keys($package->getRequires()), static function ($req): bool {
return !PlatformRepository::isPlatformPackage($req);
});


if ($isDownloadsModifyingPlugin && !count($requires)) {

array_unshift($dlModifyingPluginsNoDeps, $op);
} else {

$dlModifyingPluginRequires = array_merge($dlModifyingPluginRequires, $requires);

array_unshift($dlModifyingPluginsWithDeps, $op);
}

unset($operations[$idx]);
continue;
}


$isPlugin = $package->getType() === 'composer-plugin' || $package->getType() === 'composer-installer';


if ($isPlugin || count(array_intersect($package->getNames(), $pluginRequires))) {

$requires = array_filter(array_keys($package->getRequires()), static function ($req): bool {
return !PlatformRepository::isPlatformPackage($req);
});


if ($isPlugin && !count($requires)) {

array_unshift($pluginsNoDeps, $op);
} else {

$pluginRequires = array_merge($pluginRequires, $requires);

array_unshift($pluginsWithDeps, $op);
}

unset($operations[$idx]);
}
}

return array_merge($dlModifyingPluginsNoDeps, $dlModifyingPluginsWithDeps, $pluginsNoDeps, $pluginsWithDeps, $operations);
}








private function moveUninstallsToFront(array $operations): array
{
$uninstOps = [];
foreach ($operations as $idx => $op) {
if ($op instanceof Operation\UninstallOperation || $op instanceof Operation\MarkAliasUninstalledOperation) {
$uninstOps[] = $op;
unset($operations[$idx]);
}
}

return array_merge($uninstOps, $operations);
}
}
