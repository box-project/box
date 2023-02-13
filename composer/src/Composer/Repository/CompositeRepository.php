<?php declare(strict_types=1);











namespace Composer\Repository;

use Composer\Package\BasePackage;
use Composer\Package\PackageInterface;






class CompositeRepository implements RepositoryInterface
{




private $repositories;





public function __construct(array $repositories)
{
$this->repositories = [];
foreach ($repositories as $repo) {
$this->addRepository($repo);
}
}

public function getRepoName(): string
{
return 'composite repo ('.implode(', ', array_map(static function ($repo): string {
return $repo->getRepoName();
}, $this->repositories)).')';
}






public function getRepositories(): array
{
return $this->repositories;
}




public function hasPackage(PackageInterface $package): bool
{
foreach ($this->repositories as $repository) {

if ($repository->hasPackage($package)) {
return true;
}
}

return false;
}




public function findPackage($name, $constraint): ?BasePackage
{
foreach ($this->repositories as $repository) {

$package = $repository->findPackage($name, $constraint);
if (null !== $package) {
return $package;
}
}

return null;
}




public function findPackages($name, $constraint = null): array
{
$packages = [];
foreach ($this->repositories as $repository) {

$packages[] = $repository->findPackages($name, $constraint);
}

return $packages ? array_merge(...$packages) : [];
}




public function loadPackages(array $packageNameMap, array $acceptableStabilities, array $stabilityFlags, array $alreadyLoaded = []): array
{
$packages = [];
$namesFound = [];
foreach ($this->repositories as $repository) {

$result = $repository->loadPackages($packageNameMap, $acceptableStabilities, $stabilityFlags, $alreadyLoaded);
$packages[] = $result['packages'];
$namesFound[] = $result['namesFound'];
}

return [
'packages' => $packages ? array_merge(...$packages) : [],
'namesFound' => $namesFound ? array_unique(array_merge(...$namesFound)) : [],
];
}




public function search(string $query, int $mode = 0, ?string $type = null): array
{
$matches = [];
foreach ($this->repositories as $repository) {

$matches[] = $repository->search($query, $mode, $type);
}

return \count($matches) > 0 ? array_merge(...$matches) : [];
}




public function getPackages(): array
{
$packages = [];
foreach ($this->repositories as $repository) {

$packages[] = $repository->getPackages();
}

return $packages ? array_merge(...$packages) : [];
}




public function getProviders($packageName): array
{
$results = [];
foreach ($this->repositories as $repository) {

$results[] = $repository->getProviders($packageName);
}

return $results ? array_merge(...$results) : [];
}

public function removePackage(PackageInterface $package): void
{
foreach ($this->repositories as $repository) {
if ($repository instanceof WritableRepositoryInterface) {
$repository->removePackage($package);
}
}
}




public function count(): int
{
$total = 0;
foreach ($this->repositories as $repository) {

$total += $repository->count();
}

return $total;
}




public function addRepository(RepositoryInterface $repository): void
{
if ($repository instanceof self) {
foreach ($repository->getRepositories() as $repo) {
$this->addRepository($repo);
}
} else {
$this->repositories[] = $repository;
}
}
}
