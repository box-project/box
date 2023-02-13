<?php declare(strict_types=1);











namespace Composer\DependencyResolver;

use Composer\Package\BasePackage;
use Composer\Package\Version\VersionParser;
use Composer\Semver\CompilingMatcher;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Constraint\Constraint;







class Pool implements \Countable
{

protected $packages = [];

protected $packageByName = [];

protected $versionParser;

protected $providerCache = [];

protected $unacceptableFixedOrLockedPackages;

protected $removedVersions = [];

protected $removedVersionsByPackage = [];







public function __construct(array $packages = [], array $unacceptableFixedOrLockedPackages = [], array $removedVersions = [], array $removedVersionsByPackage = [])
{
$this->versionParser = new VersionParser;
$this->setPackages($packages);
$this->unacceptableFixedOrLockedPackages = $unacceptableFixedOrLockedPackages;
$this->removedVersions = $removedVersions;
$this->removedVersionsByPackage = $removedVersionsByPackage;
}




public function getRemovedVersions(string $name, ConstraintInterface $constraint): array
{
if (!isset($this->removedVersions[$name])) {
return [];
}

$result = [];
foreach ($this->removedVersions[$name] as $version => $prettyVersion) {
if ($constraint->matches(new Constraint('==', $version))) {
$result[$version] = $prettyVersion;
}
}

return $result;
}




public function getRemovedVersionsByPackage(string $objectHash): array
{
if (!isset($this->removedVersionsByPackage[$objectHash])) {
return [];
}

return $this->removedVersionsByPackage[$objectHash];
}




private function setPackages(array $packages): void
{
$id = 1;

foreach ($packages as $package) {
$this->packages[] = $package;

$package->id = $id++;

foreach ($package->getNames() as $provided) {
$this->packageByName[$provided][] = $package;
}
}
}




public function getPackages(): array
{
return $this->packages;
}




public function packageById(int $id): BasePackage
{
return $this->packages[$id - 1];
}




public function count(): int
{
return \count($this->packages);
}









public function whatProvides(string $name, ?ConstraintInterface $constraint = null): array
{
$key = (string) $constraint;
if (isset($this->providerCache[$name][$key])) {
return $this->providerCache[$name][$key];
}

return $this->providerCache[$name][$key] = $this->computeWhatProvides($name, $constraint);
}







private function computeWhatProvides(string $name, ?ConstraintInterface $constraint = null): array
{
if (!isset($this->packageByName[$name])) {
return [];
}

$matches = [];

foreach ($this->packageByName[$name] as $candidate) {
if ($this->match($candidate, $name, $constraint)) {
$matches[] = $candidate;
}
}

return $matches;
}

public function literalToPackage(int $literal): BasePackage
{
$packageId = abs($literal);

return $this->packageById($packageId);
}




public function literalToPrettyString(int $literal, array $installedMap): string
{
$package = $this->literalToPackage($literal);

if (isset($installedMap[$package->id])) {
$prefix = ($literal > 0 ? 'keep' : 'remove');
} else {
$prefix = ($literal > 0 ? 'install' : 'don\'t install');
}

return $prefix.' '.$package->getPrettyString();
}







public function match(BasePackage $candidate, string $name, ?ConstraintInterface $constraint = null): bool
{
$candidateName = $candidate->getName();
$candidateVersion = $candidate->getVersion();

if ($candidateName === $name) {
return $constraint === null || CompilingMatcher::match($constraint, Constraint::OP_EQ, $candidateVersion);
}

$provides = $candidate->getProvides();
$replaces = $candidate->getReplaces();


if (isset($replaces[0]) || isset($provides[0])) {
foreach ($provides as $link) {
if ($link->getTarget() === $name && ($constraint === null || $constraint->matches($link->getConstraint()))) {
return true;
}
}

foreach ($replaces as $link) {
if ($link->getTarget() === $name && ($constraint === null || $constraint->matches($link->getConstraint()))) {
return true;
}
}

return false;
}

if (isset($provides[$name]) && ($constraint === null || $constraint->matches($provides[$name]->getConstraint()))) {
return true;
}

if (isset($replaces[$name]) && ($constraint === null || $constraint->matches($replaces[$name]->getConstraint()))) {
return true;
}

return false;
}

public function isUnacceptableFixedOrLockedPackage(BasePackage $package): bool
{
return \in_array($package, $this->unacceptableFixedOrLockedPackages, true);
}




public function getUnacceptableFixedOrLockedPackages(): array
{
return $this->unacceptableFixedOrLockedPackages;
}

public function __toString(): string
{
$str = "Pool:\n";

foreach ($this->packages as $package) {
$str .= '- '.str_pad((string) $package->id, 6, ' ', STR_PAD_LEFT).': '.$package->getName()."\n";
}

return $str;
}
}
