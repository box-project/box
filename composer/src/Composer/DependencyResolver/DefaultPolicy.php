<?php declare(strict_types=1);











namespace Composer\DependencyResolver;

use Composer\Package\AliasPackage;
use Composer\Package\BasePackage;
use Composer\Package\PackageInterface;
use Composer\Semver\Constraint\Constraint;





class DefaultPolicy implements PolicyInterface
{

private $preferStable;

private $preferLowest;

private $preferredPackageResultCachePerPool;

private $sortingCachePerPool;

public function __construct(bool $preferStable = false, bool $preferLowest = false)
{
$this->preferStable = $preferStable;
$this->preferLowest = $preferLowest;
}






public function versionCompare(PackageInterface $a, PackageInterface $b, string $operator): bool
{
if ($this->preferStable && ($stabA = $a->getStability()) !== ($stabB = $b->getStability())) {
return BasePackage::$stabilities[$stabA] < BasePackage::$stabilities[$stabB];
}

$constraint = new Constraint($operator, $b->getVersion());
$version = new Constraint('==', $a->getVersion());

return $constraint->matchSpecific($version, true);
}






public function selectPreferredPackages(Pool $pool, array $literals, ?string $requiredPackage = null): array
{
sort($literals);
$resultCacheKey = implode(',', $literals).$requiredPackage;
$poolId = spl_object_id($pool);

if (isset($this->preferredPackageResultCachePerPool[$poolId][$resultCacheKey])) {
return $this->preferredPackageResultCachePerPool[$poolId][$resultCacheKey];
}

$packages = $this->groupLiteralsByName($pool, $literals);

foreach ($packages as &$nameLiterals) {
usort($nameLiterals, function ($a, $b) use ($pool, $requiredPackage, $poolId): int {
$cacheKey = 'i'.$a.'.'.$b.$requiredPackage; 

if (isset($this->sortingCachePerPool[$poolId][$cacheKey])) {
return $this->sortingCachePerPool[$poolId][$cacheKey];
}

return $this->sortingCachePerPool[$poolId][$cacheKey] = $this->compareByPriority($pool, $pool->literalToPackage($a), $pool->literalToPackage($b), $requiredPackage, true);
});
}

foreach ($packages as &$sortedLiterals) {
$sortedLiterals = $this->pruneToBestVersion($pool, $sortedLiterals);
$sortedLiterals = $this->pruneRemoteAliases($pool, $sortedLiterals);
}

$selected = array_merge(...array_values($packages));


usort($selected, function ($a, $b) use ($pool, $requiredPackage, $poolId): int {
$cacheKey = $a.'.'.$b.$requiredPackage; 

if (isset($this->sortingCachePerPool[$poolId][$cacheKey])) {
return $this->sortingCachePerPool[$poolId][$cacheKey];
}

return $this->sortingCachePerPool[$poolId][$cacheKey] = $this->compareByPriority($pool, $pool->literalToPackage($a), $pool->literalToPackage($b), $requiredPackage);
});

return $this->preferredPackageResultCachePerPool[$poolId][$resultCacheKey] = $selected;
}





protected function groupLiteralsByName(Pool $pool, array $literals): array
{
$packages = [];
foreach ($literals as $literal) {
$packageName = $pool->literalToPackage($literal)->getName();

if (!isset($packages[$packageName])) {
$packages[$packageName] = [];
}
$packages[$packageName][] = $literal;
}

return $packages;
}




public function compareByPriority(Pool $pool, BasePackage $a, BasePackage $b, ?string $requiredPackage = null, bool $ignoreReplace = false): int
{

if ($a->getName() === $b->getName()) {
$aAliased = $a instanceof AliasPackage;
$bAliased = $b instanceof AliasPackage;
if ($aAliased && !$bAliased) {
return -1; 
}
if (!$aAliased && $bAliased) {
return 1; 
}
}

if (!$ignoreReplace) {

if ($this->replaces($a, $b)) {
return 1; 
}
if ($this->replaces($b, $a)) {
return -1; 
}



if ($requiredPackage && false !== ($pos = strpos($requiredPackage, '/'))) {
$requiredVendor = substr($requiredPackage, 0, $pos);

$aIsSameVendor = strpos($a->getName(), $requiredVendor) === 0;
$bIsSameVendor = strpos($b->getName(), $requiredVendor) === 0;

if ($bIsSameVendor !== $aIsSameVendor) {
return $aIsSameVendor ? -1 : 1;
}
}
}


if ($a->id === $b->id) {
return 0;
}

return ($a->id < $b->id) ? -1 : 1;
}







protected function replaces(BasePackage $source, BasePackage $target): bool
{
foreach ($source->getReplaces() as $link) {
if ($link->getTarget() === $target->getName()


) {
return true;
}
}

return false;
}





protected function pruneToBestVersion(Pool $pool, array $literals): array
{
$operator = $this->preferLowest ? '<' : '>';
$bestLiterals = [$literals[0]];
$bestPackage = $pool->literalToPackage($literals[0]);
foreach ($literals as $i => $literal) {
if (0 === $i) {
continue;
}

$package = $pool->literalToPackage($literal);

if ($this->versionCompare($package, $bestPackage, $operator)) {
$bestPackage = $package;
$bestLiterals = [$literal];
} elseif ($this->versionCompare($package, $bestPackage, '==')) {
$bestLiterals[] = $literal;
}
}

return $bestLiterals;
}









protected function pruneRemoteAliases(Pool $pool, array $literals): array
{
$hasLocalAlias = false;

foreach ($literals as $literal) {
$package = $pool->literalToPackage($literal);

if ($package instanceof AliasPackage && $package->isRootPackageAlias()) {
$hasLocalAlias = true;
break;
}
}

if (!$hasLocalAlias) {
return $literals;
}

$selected = [];
foreach ($literals as $literal) {
$package = $pool->literalToPackage($literal);

if ($package instanceof AliasPackage && $package->isRootPackageAlias()) {
$selected[] = $literal;
}
}

return $selected;
}
}
