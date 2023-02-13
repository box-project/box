<?php declare(strict_types=1);











namespace Composer\DependencyResolver;

use Composer\Package\BasePackage;
use Composer\Package\PackageInterface;
use Composer\Repository\LockArrayRepository;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Constraint\MatchAllConstraint;




class Request
{



public const UPDATE_ONLY_LISTED = 0;





public const UPDATE_LISTED_WITH_TRANSITIVE_DEPS_NO_ROOT_REQUIRE = 1;





public const UPDATE_LISTED_WITH_TRANSITIVE_DEPS = 2;


protected $lockedRepository;

protected $requires = [];

protected $fixedPackages = [];

protected $lockedPackages = [];

protected $fixedLockedPackages = [];

protected $updateAllowList = [];

protected $updateAllowTransitiveDependencies = false;

public function __construct(?LockArrayRepository $lockedRepository = null)
{
$this->lockedRepository = $lockedRepository;
}

public function requireName(string $packageName, ?ConstraintInterface $constraint = null): void
{
$packageName = strtolower($packageName);

if ($constraint === null) {
$constraint = new MatchAllConstraint();
}
if (isset($this->requires[$packageName])) {
throw new \LogicException('Overwriting requires seems like a bug ('.$packageName.' '.$this->requires[$packageName]->getPrettyString().' => '.$constraint->getPrettyString().', check why it is happening, might be a root alias');
}
$this->requires[$packageName] = $constraint;
}







public function fixPackage(BasePackage $package): void
{
$this->fixedPackages[spl_object_hash($package)] = $package;
}











public function lockPackage(BasePackage $package): void
{
$this->lockedPackages[spl_object_hash($package)] = $package;
}








public function fixLockedPackage(BasePackage $package): void
{
$this->fixedPackages[spl_object_hash($package)] = $package;
$this->fixedLockedPackages[spl_object_hash($package)] = $package;
}

public function unlockPackage(BasePackage $package): void
{
unset($this->lockedPackages[spl_object_hash($package)]);
}





public function setUpdateAllowList(array $updateAllowList, $updateAllowTransitiveDependencies): void
{
$this->updateAllowList = $updateAllowList;
$this->updateAllowTransitiveDependencies = $updateAllowTransitiveDependencies;
}




public function getUpdateAllowList(): array
{
return $this->updateAllowList;
}

public function getUpdateAllowTransitiveDependencies(): bool
{
return $this->updateAllowTransitiveDependencies !== self::UPDATE_ONLY_LISTED;
}

public function getUpdateAllowTransitiveRootDependencies(): bool
{
return $this->updateAllowTransitiveDependencies === self::UPDATE_LISTED_WITH_TRANSITIVE_DEPS;
}




public function getRequires(): array
{
return $this->requires;
}




public function getFixedPackages(): array
{
return $this->fixedPackages;
}

public function isFixedPackage(BasePackage $package): bool
{
return isset($this->fixedPackages[spl_object_hash($package)]);
}




public function getLockedPackages(): array
{
return $this->lockedPackages;
}

public function isLockedPackage(PackageInterface $package): bool
{
return isset($this->lockedPackages[spl_object_hash($package)]) || isset($this->fixedLockedPackages[spl_object_hash($package)]);
}




public function getFixedOrLockedPackages(): array
{
return array_merge($this->fixedPackages, $this->lockedPackages);
}









public function getPresentMap(bool $packageIds = false): array
{
$presentMap = [];

if ($this->lockedRepository) {
foreach ($this->lockedRepository->getPackages() as $package) {
$presentMap[$packageIds ? $package->getId() : spl_object_hash($package)] = $package;
}
}

foreach ($this->fixedPackages as $package) {
$presentMap[$packageIds ? $package->getId() : spl_object_hash($package)] = $package;
}

return $presentMap;
}




public function getFixedPackagesMap(): array
{
$fixedPackagesMap = [];

foreach ($this->fixedPackages as $package) {
$fixedPackagesMap[$package->getId()] = $package;
}

return $fixedPackagesMap;
}




public function getLockedRepository(): ?LockArrayRepository
{
return $this->lockedRepository;
}
}
