<?php declare(strict_types=1);











namespace Composer\DependencyResolver;

use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Package\AliasPackage;
use Composer\Package\BasePackage;
use Composer\Package\CompleteAliasPackage;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Composer\Package\Version\StabilityFilter;
use Composer\Pcre\Preg;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PrePoolCreateEvent;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RootPackageRepository;
use Composer\Semver\CompilingMatcher;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Constraint\MatchAllConstraint;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\Intervals;




class PoolBuilder
{




private $acceptableStabilities;




private $stabilityFlags;




private $rootAliases;




private $rootReferences;



private $temporaryConstraints;



private $eventDispatcher;



private $poolOptimizer;



private $io;




private $aliasMap = [];




private $packagesToLoad = [];




private $loadedPackages = [];




private $loadedPerRepo = [];



private $packages = [];



private $unacceptableFixedOrLockedPackages = [];

private $updateAllowList = [];

private $skippedLoad = [];









private $pathRepoUnlocked = [];











private $maxExtendedReqs = [];




private $updateAllowWarned = [];


private $indexCounter = 0;












public function __construct(array $acceptableStabilities, array $stabilityFlags, array $rootAliases, array $rootReferences, IOInterface $io, ?EventDispatcher $eventDispatcher = null, ?PoolOptimizer $poolOptimizer = null, array $temporaryConstraints = [])
{
$this->acceptableStabilities = $acceptableStabilities;
$this->stabilityFlags = $stabilityFlags;
$this->rootAliases = $rootAliases;
$this->rootReferences = $rootReferences;
$this->eventDispatcher = $eventDispatcher;
$this->poolOptimizer = $poolOptimizer;
$this->io = $io;
$this->temporaryConstraints = $temporaryConstraints;
}




public function buildPool(array $repositories, Request $request): Pool
{
if ($request->getUpdateAllowList()) {
$this->updateAllowList = $request->getUpdateAllowList();
$this->warnAboutNonMatchingUpdateAllowList($request);

foreach ($request->getLockedRepository()->getPackages() as $lockedPackage) {
if (!$this->isUpdateAllowed($lockedPackage)) {

$this->skippedLoad[$lockedPackage->getName()][] = $lockedPackage;
foreach ($lockedPackage->getReplaces() as $link) {
$this->skippedLoad[$link->getTarget()][] = $lockedPackage;
}





if ($lockedPackage->getDistType() === 'path') {
$transportOptions = $lockedPackage->getTransportOptions();
if (!isset($transportOptions['symlink']) || $transportOptions['symlink'] !== false) {
$this->pathRepoUnlocked[$lockedPackage->getName()] = true;
continue;
}
}

$request->lockPackage($lockedPackage);
}
}
}

foreach ($request->getFixedOrLockedPackages() as $package) {


$this->loadedPackages[$package->getName()] = new MatchAllConstraint();


foreach ($package->getReplaces() as $link) {
$this->loadedPackages[$link->getTarget()] = new MatchAllConstraint();
}




if (
$package->getRepository() instanceof RootPackageRepository
|| $package->getRepository() instanceof PlatformRepository
|| StabilityFilter::isPackageAcceptable($this->acceptableStabilities, $this->stabilityFlags, $package->getNames(), $package->getStability())
) {
$this->loadPackage($request, $repositories, $package, false);
} else {
$this->unacceptableFixedOrLockedPackages[] = $package;
}
}

foreach ($request->getRequires() as $packageName => $constraint) {

if (isset($this->loadedPackages[$packageName])) {
continue;
}

$this->packagesToLoad[$packageName] = $constraint;
$this->maxExtendedReqs[$packageName] = true;
}


foreach ($this->packagesToLoad as $name => $constraint) {
if (isset($this->loadedPackages[$name])) {
unset($this->packagesToLoad[$name]);
}
}

while (!empty($this->packagesToLoad)) {
$this->loadPackagesMarkedForLoading($request, $repositories);
}

if (\count($this->temporaryConstraints) > 0) {
foreach ($this->packages as $i => $package) {

if (!isset($this->temporaryConstraints[$package->getName()]) || $package instanceof AliasPackage) {
continue;
}

$constraint = $this->temporaryConstraints[$package->getName()];
$packageAndAliases = [$i => $package];
if (isset($this->aliasMap[spl_object_hash($package)])) {
$packageAndAliases += $this->aliasMap[spl_object_hash($package)];
}

$found = false;
foreach ($packageAndAliases as $packageOrAlias) {
if (CompilingMatcher::match($constraint, Constraint::OP_EQ, $packageOrAlias->getVersion())) {
$found = true;
}
}

if (!$found) {
foreach ($packageAndAliases as $index => $packageOrAlias) {
unset($this->packages[$index]);
}
}
}
}

if ($this->eventDispatcher) {
$prePoolCreateEvent = new PrePoolCreateEvent(
PluginEvents::PRE_POOL_CREATE,
$repositories,
$request,
$this->acceptableStabilities,
$this->stabilityFlags,
$this->rootAliases,
$this->rootReferences,
$this->packages,
$this->unacceptableFixedOrLockedPackages
);
$this->eventDispatcher->dispatch($prePoolCreateEvent->getName(), $prePoolCreateEvent);
$this->packages = $prePoolCreateEvent->getPackages();
$this->unacceptableFixedOrLockedPackages = $prePoolCreateEvent->getUnacceptableFixedPackages();
}

$pool = new Pool($this->packages, $this->unacceptableFixedOrLockedPackages);

$this->aliasMap = [];
$this->packagesToLoad = [];
$this->loadedPackages = [];
$this->loadedPerRepo = [];
$this->packages = [];
$this->unacceptableFixedOrLockedPackages = [];
$this->maxExtendedReqs = [];
$this->skippedLoad = [];
$this->indexCounter = 0;

$this->io->debug('Built pool.');

$pool = $this->runOptimizer($request, $pool);

Intervals::clear();

return $pool;
}

private function markPackageNameForLoading(Request $request, string $name, ConstraintInterface $constraint): void
{

if (PlatformRepository::isPlatformPackage($name)) {
return;
}



if (isset($this->maxExtendedReqs[$name])) {
return;
}





$rootRequires = $request->getRequires();
if (isset($rootRequires[$name]) && !Intervals::isSubsetOf($constraint, $rootRequires[$name])) {
$constraint = $rootRequires[$name];
}


if (!isset($this->loadedPackages[$name])) {



if (isset($this->packagesToLoad[$name])) {

if (Intervals::isSubsetOf($constraint, $this->packagesToLoad[$name])) {
return;
}


$constraint = Intervals::compactConstraint(MultiConstraint::create([$this->packagesToLoad[$name], $constraint], false));
}

$this->packagesToLoad[$name] = $constraint;

return;
}



if (Intervals::isSubsetOf($constraint, $this->loadedPackages[$name])) {
return;
}




$this->packagesToLoad[$name] = Intervals::compactConstraint(MultiConstraint::create([$this->loadedPackages[$name], $constraint], false));
unset($this->loadedPackages[$name]);
}




private function loadPackagesMarkedForLoading(Request $request, array $repositories): void
{
foreach ($this->packagesToLoad as $name => $constraint) {
$this->loadedPackages[$name] = $constraint;
}

$packageBatch = $this->packagesToLoad;
$this->packagesToLoad = [];

foreach ($repositories as $repoIndex => $repository) {
if (empty($packageBatch)) {
break;
}



if ($repository instanceof PlatformRepository || $repository === $request->getLockedRepository()) {
continue;
}
$result = $repository->loadPackages($packageBatch, $this->acceptableStabilities, $this->stabilityFlags, $this->loadedPerRepo[$repoIndex] ?? []);

foreach ($result['namesFound'] as $name) {

unset($packageBatch[$name]);
}
foreach ($result['packages'] as $package) {
$this->loadedPerRepo[$repoIndex][$package->getName()][$package->getVersion()] = $package;
$this->loadPackage($request, $repositories, $package, !isset($this->pathRepoUnlocked[$package->getName()]));
}
}
}




private function loadPackage(Request $request, array $repositories, BasePackage $package, bool $propagateUpdate): void
{
$index = $this->indexCounter++;
$this->packages[$index] = $package;

if ($package instanceof AliasPackage) {
$this->aliasMap[spl_object_hash($package->getAliasOf())][$index] = $package;
}

$name = $package->getName();




if (isset($this->rootReferences[$name])) {

if (!$request->isLockedPackage($package) && !$request->isFixedPackage($package)) {
$package->setSourceDistReferences($this->rootReferences[$name]);
}
}



if ($propagateUpdate && isset($this->rootAliases[$name][$package->getVersion()])) {
$alias = $this->rootAliases[$name][$package->getVersion()];
if ($package instanceof AliasPackage) {
$basePackage = $package->getAliasOf();
} else {
$basePackage = $package;
}
if ($basePackage instanceof CompletePackage) {
$aliasPackage = new CompleteAliasPackage($basePackage, $alias['alias_normalized'], $alias['alias']);
} else {
$aliasPackage = new AliasPackage($basePackage, $alias['alias_normalized'], $alias['alias']);
}
$aliasPackage->setRootPackageAlias(true);

$newIndex = $this->indexCounter++;
$this->packages[$newIndex] = $aliasPackage;
$this->aliasMap[spl_object_hash($aliasPackage->getAliasOf())][$newIndex] = $aliasPackage;
}

foreach ($package->getRequires() as $link) {
$require = $link->getTarget();
$linkConstraint = $link->getConstraint();


if (isset($this->skippedLoad[$require])) {



if ($propagateUpdate && $request->getUpdateAllowTransitiveDependencies()) {
$skippedRootRequires = $this->getSkippedRootRequires($request, $require);

if ($request->getUpdateAllowTransitiveRootDependencies() || !$skippedRootRequires) {
$this->unlockPackage($request, $repositories, $require);
$this->markPackageNameForLoading($request, $require, $linkConstraint);
} else {
foreach ($skippedRootRequires as $rootRequire) {
if (!isset($this->updateAllowWarned[$rootRequire])) {
$this->updateAllowWarned[$rootRequire] = true;
$this->io->writeError('<warning>Dependency '.$rootRequire.' is also a root requirement. Package has not been listed as an update argument, so keeping locked at old version. Use --with-all-dependencies (-W) to include root dependencies.</warning>');
}
}
}
} elseif (isset($this->pathRepoUnlocked[$require]) && !isset($this->loadedPackages[$require])) {


$this->markPackageNameForLoading($request, $require, $linkConstraint);
}
} else {
$this->markPackageNameForLoading($request, $require, $linkConstraint);
}
}



if ($propagateUpdate && $request->getUpdateAllowTransitiveDependencies()) {
foreach ($package->getReplaces() as $link) {
$replace = $link->getTarget();
if (isset($this->loadedPackages[$replace], $this->skippedLoad[$replace])) {
$skippedRootRequires = $this->getSkippedRootRequires($request, $replace);

if ($request->getUpdateAllowTransitiveRootDependencies() || !$skippedRootRequires) {
$this->unlockPackage($request, $repositories, $replace);
$this->markPackageNameForLoading($request, $replace, $link->getConstraint());
} else {
foreach ($skippedRootRequires as $rootRequire) {
if (!isset($this->updateAllowWarned[$rootRequire])) {
$this->updateAllowWarned[$rootRequire] = true;
$this->io->writeError('<warning>Dependency '.$rootRequire.' is also a root requirement. Package has not been listed as an update argument, so keeping locked at old version. Use --with-all-dependencies (-W) to include root dependencies.</warning>');
}
}
}
}
}
}
}






private function isRootRequire(Request $request, string $name): bool
{
$rootRequires = $request->getRequires();

return isset($rootRequires[$name]);
}




private function getSkippedRootRequires(Request $request, string $name): array
{
if (!isset($this->skippedLoad[$name])) {
return [];
}

$rootRequires = $request->getRequires();
$matches = [];

if (isset($rootRequires[$name])) {
return array_map(static function (PackageInterface $package) use ($name): string {
if ($name !== $package->getName()) {
return $package->getName() .' (via replace of '.$name.')';
}

return $package->getName();
}, $this->skippedLoad[$name]);
}

foreach ($this->skippedLoad[$name] as $packageOrReplacer) {
if (isset($rootRequires[$packageOrReplacer->getName()])) {
$matches[] = $packageOrReplacer->getName();
}
foreach ($packageOrReplacer->getReplaces() as $link) {
if (isset($rootRequires[$link->getTarget()])) {
if ($name !== $packageOrReplacer->getName()) {
$matches[] = $packageOrReplacer->getName() .' (via replace of '.$name.')';
} else {
$matches[] = $packageOrReplacer->getName();
}
break;
}
}
}

return $matches;
}




private function isUpdateAllowed(BasePackage $package): bool
{
foreach ($this->updateAllowList as $pattern => $void) {
$patternRegexp = BasePackage::packageNameToRegexp($pattern);
if (Preg::isMatch($patternRegexp, $package->getName())) {
return true;
}
}

return false;
}

private function warnAboutNonMatchingUpdateAllowList(Request $request): void
{
foreach ($this->updateAllowList as $pattern => $void) {
$patternRegexp = BasePackage::packageNameToRegexp($pattern);

foreach ($request->getLockedRepository()->getPackages() as $package) {
if (Preg::isMatch($patternRegexp, $package->getName())) {
continue 2;
}
}

foreach ($request->getRequires() as $packageName => $constraint) {
if (Preg::isMatch($patternRegexp, $packageName)) {
continue 2;
}
}
if (strpos($pattern, '*') !== false) {
$this->io->writeError('<warning>Pattern "' . $pattern . '" listed for update does not match any locked packages.</warning>');
} else {
$this->io->writeError('<warning>Package "' . $pattern . '" listed for update is not locked.</warning>');
}
}
}







private function unlockPackage(Request $request, array $repositories, string $name): void
{
foreach ($this->skippedLoad[$name] as $packageOrReplacer) {


if ($packageOrReplacer->getName() !== $name && isset($this->skippedLoad[$packageOrReplacer->getName()])) {
$replacerName = $packageOrReplacer->getName();
if ($request->getUpdateAllowTransitiveRootDependencies() || (!$this->isRootRequire($request, $name) && !$this->isRootRequire($request, $replacerName))) {
$this->unlockPackage($request, $repositories, $replacerName);

if ($this->isRootRequire($request, $replacerName)) {
$this->markPackageNameForLoading($request, $replacerName, new MatchAllConstraint);
} else {
foreach ($this->packages as $loadedPackage) {
$requires = $loadedPackage->getRequires();
if (isset($requires[$replacerName])) {
$this->markPackageNameForLoading($request, $replacerName, $requires[$replacerName]->getConstraint());
}
}
}
}
}
}

if (isset($this->pathRepoUnlocked[$name])) {
foreach ($this->packages as $index => $package) {
if ($package->getName() === $name) {
$this->removeLoadedPackage($request, $repositories, $package, $index);
}
}
}

unset($this->skippedLoad[$name], $this->loadedPackages[$name], $this->maxExtendedReqs[$name], $this->pathRepoUnlocked[$name]);


foreach ($request->getLockedPackages() as $lockedPackage) {
if (!($lockedPackage instanceof AliasPackage) && $lockedPackage->getName() === $name) {
if (false !== $index = array_search($lockedPackage, $this->packages, true)) {
$request->unlockPackage($lockedPackage);
$this->removeLoadedPackage($request, $repositories, $lockedPackage, $index);




foreach ($request->getFixedOrLockedPackages() as $fixedOrLockedPackage) {
if ($fixedOrLockedPackage === $lockedPackage) {
continue;
}

if (isset($this->skippedLoad[$fixedOrLockedPackage->getName()])) {
$requires = $fixedOrLockedPackage->getRequires();
if (isset($requires[$lockedPackage->getName()])) {
$this->markPackageNameForLoading($request, $lockedPackage->getName(), $requires[$lockedPackage->getName()]->getConstraint());
}
}
}
}
}
}
}




private function removeLoadedPackage(Request $request, array $repositories, BasePackage $package, int $index): void
{
$repoIndex = array_search($package->getRepository(), $repositories, true);

unset($this->loadedPerRepo[$repoIndex][$package->getName()][$package->getVersion()]);
unset($this->packages[$index]);
if (isset($this->aliasMap[spl_object_hash($package)])) {
foreach ($this->aliasMap[spl_object_hash($package)] as $aliasIndex => $aliasPackage) {
unset($this->loadedPerRepo[$repoIndex][$aliasPackage->getName()][$aliasPackage->getVersion()]);
unset($this->packages[$aliasIndex]);
}
unset($this->aliasMap[spl_object_hash($package)]);
}
}

private function runOptimizer(Request $request, Pool $pool): Pool
{
if (null === $this->poolOptimizer) {
return $pool;
}

$this->io->debug('Running pool optimizer.');

$before = microtime(true);
$total = \count($pool->getPackages());

$pool = $this->poolOptimizer->optimize($request, $pool);

$filtered = $total - \count($pool->getPackages());

if (0 === $filtered) {
return $pool;
}

$this->io->write(sprintf('Pool optimizer completed in %.3f seconds', microtime(true) - $before), true, IOInterface::VERY_VERBOSE);
$this->io->write(sprintf(
'<info>Found %s package versions referenced in your dependency graph. %s (%d%%) were optimized away.</info>',
number_format($total),
number_format($filtered),
round(100 / $total * $filtered)
), true, IOInterface::VERY_VERBOSE);

return $pool;
}
}
