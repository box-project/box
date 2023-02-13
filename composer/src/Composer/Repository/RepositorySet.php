<?php declare(strict_types=1);











namespace Composer\Repository;

use Composer\DependencyResolver\PoolOptimizer;
use Composer\DependencyResolver\Pool;
use Composer\DependencyResolver\PoolBuilder;
use Composer\DependencyResolver\Request;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Advisory\SecurityAdvisory;
use Composer\Advisory\PartialSecurityAdvisory;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Package\BasePackage;
use Composer\Package\AliasPackage;
use Composer\Package\CompleteAliasPackage;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Package\Version\StabilityFilter;
use Composer\Semver\Constraint\MatchAllConstraint;






class RepositorySet
{



public const ALLOW_UNACCEPTABLE_STABILITIES = 1;



public const ALLOW_SHADOWED_REPOSITORIES = 2;





private $rootAliases;





private $rootReferences;


private $repositories = [];





private $acceptableStabilities;





private $stabilityFlags;





private $rootRequires;




private $temporaryConstraints;


private $locked = false;

private $allowInstalledRepositories = false;
















public function __construct(string $minimumStability = 'stable', array $stabilityFlags = [], array $rootAliases = [], array $rootReferences = [], array $rootRequires = [], array $temporaryConstraints = [])
{
$this->rootAliases = self::getRootAliasesPerPackage($rootAliases);
$this->rootReferences = $rootReferences;

$this->acceptableStabilities = [];
foreach (BasePackage::$stabilities as $stability => $value) {
if ($value <= BasePackage::$stabilities[$minimumStability]) {
$this->acceptableStabilities[$stability] = $value;
}
}
$this->stabilityFlags = $stabilityFlags;
$this->rootRequires = $rootRequires;
foreach ($rootRequires as $name => $constraint) {
if (PlatformRepository::isPlatformPackage($name)) {
unset($this->rootRequires[$name]);
}
}

$this->temporaryConstraints = $temporaryConstraints;
}

public function allowInstalledRepositories(bool $allow = true): void
{
$this->allowInstalledRepositories = $allow;
}





public function getRootRequires(): array
{
return $this->rootRequires;
}




public function getTemporaryConstraints(): array
{
return $this->temporaryConstraints;
}









public function addRepository(RepositoryInterface $repo): void
{
if ($this->locked) {
throw new \RuntimeException("Pool has already been created from this repository set, it cannot be modified anymore.");
}

if ($repo instanceof CompositeRepository) {
$repos = $repo->getRepositories();
} else {
$repos = [$repo];
}

foreach ($repos as $repo) {
$this->repositories[] = $repo;
}
}









public function findPackages(string $name, ?ConstraintInterface $constraint = null, int $flags = 0): array
{
$ignoreStability = ($flags & self::ALLOW_UNACCEPTABLE_STABILITIES) !== 0;
$loadFromAllRepos = ($flags & self::ALLOW_SHADOWED_REPOSITORIES) !== 0;

$packages = [];
if ($loadFromAllRepos) {
foreach ($this->repositories as $repository) {
$packages[] = $repository->findPackages($name, $constraint) ?: [];
}
} else {
foreach ($this->repositories as $repository) {
$result = $repository->loadPackages([$name => $constraint], $ignoreStability ? BasePackage::$stabilities : $this->acceptableStabilities, $ignoreStability ? [] : $this->stabilityFlags);

$packages[] = $result['packages'];
foreach ($result['namesFound'] as $nameFound) {

if ($name === $nameFound) {
break 2;
}
}
}
}

$candidates = $packages ? array_merge(...$packages) : [];


if ($ignoreStability || !$loadFromAllRepos) {
return $candidates;
}

$result = [];
foreach ($candidates as $candidate) {
if ($this->isPackageAcceptable($candidate->getNames(), $candidate->getStability())) {
$result[] = $candidate;
}
}

return $result;
}





public function getSecurityAdvisories(array $packageNames, bool $allowPartialAdvisories = false): array
{
$map = [];
foreach ($packageNames as $name) {
$map[$name] = new MatchAllConstraint();
}

return $this->getSecurityAdvisoriesForConstraints($map, $allowPartialAdvisories);
}





public function getMatchingSecurityAdvisories(array $packages, bool $allowPartialAdvisories = false): array
{
$map = [];
foreach ($packages as $package) {
$map[$package->getName()] = new Constraint('=', $package->getVersion());
}

return $this->getSecurityAdvisoriesForConstraints($map, $allowPartialAdvisories);
}





private function getSecurityAdvisoriesForConstraints(array $packageConstraintMap, bool $allowPartialAdvisories): array
{
$advisories = [];
foreach ($this->repositories as $repository) {
if (!$repository instanceof AdvisoryProviderInterface || !$repository->hasSecurityAdvisories()) {
continue;
}

$result = $repository->getSecurityAdvisories($packageConstraintMap, $allowPartialAdvisories);
foreach ($result['namesFound'] as $nameFound) {
unset($packageConstraintMap[$nameFound]);
}

$advisories = array_merge($advisories, $result['advisories']);
}

return $advisories;
}





public function getProviders(string $packageName): array
{
$providers = [];
foreach ($this->repositories as $repository) {
if ($repoProviders = $repository->getProviders($packageName)) {
$providers = array_merge($providers, $repoProviders);
}
}

return $providers;
}







public function isPackageAcceptable(array $names, string $stability): bool
{
return StabilityFilter::isPackageAcceptable($this->acceptableStabilities, $this->stabilityFlags, $names, $stability);
}




public function createPool(Request $request, IOInterface $io, ?EventDispatcher $eventDispatcher = null, ?PoolOptimizer $poolOptimizer = null): Pool
{
$poolBuilder = new PoolBuilder($this->acceptableStabilities, $this->stabilityFlags, $this->rootAliases, $this->rootReferences, $io, $eventDispatcher, $poolOptimizer, $this->temporaryConstraints);

foreach ($this->repositories as $repo) {
if (($repo instanceof InstalledRepositoryInterface || $repo instanceof InstalledRepository) && !$this->allowInstalledRepositories) {
throw new \LogicException('The pool can not accept packages from an installed repository');
}
}

$this->locked = true;

return $poolBuilder->buildPool($this->repositories, $request);
}




public function createPoolWithAllPackages(): Pool
{
foreach ($this->repositories as $repo) {
if (($repo instanceof InstalledRepositoryInterface || $repo instanceof InstalledRepository) && !$this->allowInstalledRepositories) {
throw new \LogicException('The pool can not accept packages from an installed repository');
}
}

$this->locked = true;

$packages = [];
foreach ($this->repositories as $repository) {
foreach ($repository->getPackages() as $package) {
$packages[] = $package;

if (isset($this->rootAliases[$package->getName()][$package->getVersion()])) {
$alias = $this->rootAliases[$package->getName()][$package->getVersion()];
while ($package instanceof AliasPackage) {
$package = $package->getAliasOf();
}
if ($package instanceof CompletePackage) {
$aliasPackage = new CompleteAliasPackage($package, $alias['alias_normalized'], $alias['alias']);
} else {
$aliasPackage = new AliasPackage($package, $alias['alias_normalized'], $alias['alias']);
}
$aliasPackage->setRootPackageAlias(true);
$packages[] = $aliasPackage;
}
}
}

return new Pool($packages);
}

public function createPoolForPackage(string $packageName, ?LockArrayRepository $lockedRepo = null): Pool
{

return $this->createPoolForPackages([$packageName], $lockedRepo);
}




public function createPoolForPackages(array $packageNames, ?LockArrayRepository $lockedRepo = null): Pool
{
$request = new Request($lockedRepo);

foreach ($packageNames as $packageName) {
if (PlatformRepository::isPlatformPackage($packageName)) {
throw new \LogicException('createPoolForPackage(s) can not be used for platform packages, as they are never loaded by the PoolBuilder which expects them to be fixed. Use createPoolWithAllPackages or pass in a proper request with the platform packages you need fixed in it.');
}

$request->requireName($packageName);
}

return $this->createPool($request, new NullIO());
}







private static function getRootAliasesPerPackage(array $aliases): array
{
$normalizedAliases = [];

foreach ($aliases as $alias) {
$normalizedAliases[$alias['package']][$alias['version']] = [
'alias' => $alias['alias'],
'alias_normalized' => $alias['alias_normalized'],
];
}

return $normalizedAliases;
}
}
