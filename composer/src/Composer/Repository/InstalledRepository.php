<?php declare(strict_types=1);











namespace Composer\Repository;

use Composer\Package\BasePackage;
use Composer\Package\PackageInterface;
use Composer\Package\Version\VersionParser;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\MatchAllConstraint;
use Composer\Package\RootPackageInterface;
use Composer\Package\Link;










class InstalledRepository extends CompositeRepository
{





public function findPackagesWithReplacersAndProviders(string $name, $constraint = null): array
{
$name = strtolower($name);

if (null !== $constraint && !$constraint instanceof ConstraintInterface) {
$versionParser = new VersionParser();
$constraint = $versionParser->parseConstraints($constraint);
}

$matches = [];
foreach ($this->getRepositories() as $repo) {
foreach ($repo->getPackages() as $candidate) {
if ($name === $candidate->getName()) {
if (null === $constraint || $constraint->matches(new Constraint('==', $candidate->getVersion()))) {
$matches[] = $candidate;
}
continue;
}

foreach (array_merge($candidate->getProvides(), $candidate->getReplaces()) as $link) {
if (
$name === $link->getTarget()
&& ($constraint === null || $constraint->matches($link->getConstraint()))
) {
$matches[] = $candidate;
continue 2;
}
}
}
}

return $matches;
}
















public function getDependents($needle, ?ConstraintInterface $constraint = null, bool $invert = false, bool $recurse = true, ?array $packagesFound = null): array
{
$needles = array_map('strtolower', (array) $needle);
$results = [];


if (null === $packagesFound) {
$packagesFound = $needles;
}


$rootPackage = null;
foreach ($this->getPackages() as $package) {
if ($package instanceof RootPackageInterface) {
$rootPackage = $package;
break;
}
}


foreach ($this->getPackages() as $package) {
$links = $package->getRequires();



$packagesInTree = $packagesFound;


if (!$invert) {
$links += $package->getReplaces();




foreach ($package->getReplaces() as $link) {
foreach ($needles as $needle) {
if ($link->getSource() === $needle) {
if ($constraint === null || ($link->getConstraint()->matches($constraint) === true)) {

if (in_array($link->getTarget(), $packagesInTree)) {
$results[] = [$package, $link, false];
continue;
}
$packagesInTree[] = $link->getTarget();
$dependents = $recurse ? $this->getDependents($link->getTarget(), null, false, true, $packagesInTree) : [];
$results[] = [$package, $link, $dependents];
$needles[] = $link->getTarget();
}
}
}
}
}


if ($package instanceof RootPackageInterface) {
$links += $package->getDevRequires();
}


foreach ($links as $link) {
foreach ($needles as $needle) {
if ($link->getTarget() === $needle) {
if ($constraint === null || ($link->getConstraint()->matches($constraint) === !$invert)) {

if (in_array($link->getSource(), $packagesInTree)) {
$results[] = [$package, $link, false];
continue;
}
$packagesInTree[] = $link->getSource();
$dependents = $recurse ? $this->getDependents($link->getSource(), null, false, true, $packagesInTree) : [];
$results[] = [$package, $link, $dependents];
}
}
}
}


if ($invert && in_array($package->getName(), $needles)) {
foreach ($package->getConflicts() as $link) {
foreach ($this->findPackages($link->getTarget()) as $pkg) {
$version = new Constraint('=', $pkg->getVersion());
if ($link->getConstraint()->matches($version) === $invert) {
$results[] = [$package, $link, false];
}
}
}
}


foreach ($package->getConflicts() as $link) {
if (in_array($link->getTarget(), $needles)) {
foreach ($this->findPackages($link->getTarget()) as $pkg) {
$version = new Constraint('=', $pkg->getVersion());
if ($link->getConstraint()->matches($version) === $invert) {
$results[] = [$package, $link, false];
}
}
}
}


if ($invert && $constraint && in_array($package->getName(), $needles) && $constraint->matches(new Constraint('=', $package->getVersion()))) {
foreach ($package->getRequires() as $link) {
if (PlatformRepository::isPlatformPackage($link->getTarget())) {
if ($this->findPackage($link->getTarget(), $link->getConstraint())) {
continue;
}

$platformPkg = $this->findPackage($link->getTarget(), '*');
$description = $platformPkg ? 'but '.$platformPkg->getPrettyVersion().' is installed' : 'but it is missing';
$results[] = [$package, new Link($package->getName(), $link->getTarget(), new MatchAllConstraint, Link::TYPE_REQUIRE, $link->getPrettyConstraint().' '.$description), false];

continue;
}

foreach ($this->getPackages() as $pkg) {
if (!in_array($link->getTarget(), $pkg->getNames())) {
continue;
}

$version = new Constraint('=', $pkg->getVersion());

if ($link->getTarget() !== $pkg->getName()) {
foreach (array_merge($pkg->getReplaces(), $pkg->getProvides()) as $prov) {
if ($link->getTarget() === $prov->getTarget()) {
$version = $prov->getConstraint();
break;
}
}
}

if (!$link->getConstraint()->matches($version)) {


if ($rootPackage) {
foreach (array_merge($rootPackage->getRequires(), $rootPackage->getDevRequires()) as $rootReq) {
if (in_array($rootReq->getTarget(), $pkg->getNames()) && !$rootReq->getConstraint()->matches($link->getConstraint())) {
$results[] = [$package, $link, false];
$results[] = [$rootPackage, $rootReq, false];
continue 3;
}
}

$results[] = [$package, $link, false];
$results[] = [$rootPackage, new Link($rootPackage->getName(), $link->getTarget(), new MatchAllConstraint, Link::TYPE_DOES_NOT_REQUIRE, 'but ' . $pkg->getPrettyVersion() . ' is installed'), false];
} else {

$results[] = [$package, $link, false];
}
}

continue 2;
}
}
}
}

ksort($results);

return $results;
}

public function getRepoName(): string
{
return 'installed repo ('.implode(', ', array_map(static function ($repo): string {
return $repo->getRepoName();
}, $this->getRepositories())).')';
}




public function addRepository(RepositoryInterface $repository): void
{
if (
$repository instanceof LockArrayRepository
|| $repository instanceof InstalledRepositoryInterface
|| $repository instanceof RootPackageRepository
|| $repository instanceof PlatformRepository
) {
parent::addRepository($repository);

return;
}

throw new \LogicException('An InstalledRepository can not contain a repository of type '.get_class($repository).' ('.$repository->getRepoName().')');
}
}
