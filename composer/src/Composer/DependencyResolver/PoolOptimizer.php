<?php declare(strict_types=1);











namespace Composer\DependencyResolver;

use Composer\Package\AliasPackage;
use Composer\Package\BasePackage;
use Composer\Package\Version\VersionParser;
use Composer\Semver\CompilingMatcher;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\Intervals;






class PoolOptimizer
{



private $policy;




private $irremovablePackages = [];




private $requireConstraintsPerPackage = [];




private $conflictConstraintsPerPackage = [];




private $packagesToRemove = [];




private $aliasesPerPackage = [];




private $removedVersionsByPackage = [];

public function __construct(PolicyInterface $policy)
{
$this->policy = $policy;
}

public function optimize(Request $request, Pool $pool): Pool
{
$this->prepare($request, $pool);

$this->optimizeByIdenticalDependencies($request, $pool);

$this->optimizeImpossiblePackagesAway($request, $pool);

$optimizedPool = $this->applyRemovalsToPool($pool);






$this->irremovablePackages = [];
$this->requireConstraintsPerPackage = [];
$this->conflictConstraintsPerPackage = [];
$this->packagesToRemove = [];
$this->aliasesPerPackage = [];
$this->removedVersionsByPackage = [];

return $optimizedPool;
}

private function prepare(Request $request, Pool $pool): void
{
$irremovablePackageConstraintGroups = [];


foreach ($request->getFixedOrLockedPackages() as $package) {
$irremovablePackageConstraintGroups[$package->getName()][] = new Constraint('==', $package->getVersion());
}


foreach ($request->getRequires() as $require => $constraint) {
$this->extractRequireConstraintsPerPackage($require, $constraint);
}


foreach ($pool->getPackages() as $package) {

foreach ($package->getRequires() as $link) {
$this->extractRequireConstraintsPerPackage($link->getTarget(), $link->getConstraint());
}

foreach ($package->getConflicts() as $link) {
$this->extractConflictConstraintsPerPackage($link->getTarget(), $link->getConstraint());
}



if ($package instanceof AliasPackage) {
$this->aliasesPerPackage[$package->getAliasOf()->id][] = $package;
}
}

$irremovablePackageConstraints = [];
foreach ($irremovablePackageConstraintGroups as $packageName => $constraints) {
$irremovablePackageConstraints[$packageName] = 1 === \count($constraints) ? $constraints[0] : new MultiConstraint($constraints, false);
}
unset($irremovablePackageConstraintGroups);


foreach ($pool->getPackages() as $package) {
if (!isset($irremovablePackageConstraints[$package->getName()])) {
continue;
}

if (CompilingMatcher::match($irremovablePackageConstraints[$package->getName()], Constraint::OP_EQ, $package->getVersion())) {
$this->markPackageIrremovable($package);
}
}
}

private function markPackageIrremovable(BasePackage $package): void
{
$this->irremovablePackages[$package->id] = true;
if ($package instanceof AliasPackage) {


$this->markPackageIrremovable($package->getAliasOf());
}
if (isset($this->aliasesPerPackage[$package->id])) {
foreach ($this->aliasesPerPackage[$package->id] as $aliasPackage) {
$this->irremovablePackages[$aliasPackage->id] = true;
}
}
}




private function applyRemovalsToPool(Pool $pool): Pool
{
$packages = [];
$removedVersions = [];
foreach ($pool->getPackages() as $package) {
if (!isset($this->packagesToRemove[$package->id])) {
$packages[] = $package;
} else {
$removedVersions[$package->getName()][$package->getVersion()] = $package->getPrettyVersion();
}
}

$optimizedPool = new Pool($packages, $pool->getUnacceptableFixedOrLockedPackages(), $removedVersions, $this->removedVersionsByPackage);

return $optimizedPool;
}

private function optimizeByIdenticalDependencies(Request $request, Pool $pool): void
{
$identicalDefinitionsPerPackage = [];
$packageIdenticalDefinitionLookup = [];

foreach ($pool->getPackages() as $package) {



if (isset($this->irremovablePackages[$package->id])) {
continue;
}

$this->markPackageForRemoval($package->id);

$dependencyHash = $this->calculateDependencyHash($package);

foreach ($package->getNames(false) as $packageName) {
if (!isset($this->requireConstraintsPerPackage[$packageName])) {
continue;
}

foreach ($this->requireConstraintsPerPackage[$packageName] as $requireConstraint) {
$groupHashParts = [];

if (CompilingMatcher::match($requireConstraint, Constraint::OP_EQ, $package->getVersion())) {
$groupHashParts[] = 'require:' . (string) $requireConstraint;
}

if ($package->getReplaces()) {
foreach ($package->getReplaces() as $link) {
if (CompilingMatcher::match($link->getConstraint(), Constraint::OP_EQ, $package->getVersion())) {

$groupHashParts[] = 'require:' . (string) $link->getConstraint();
}
}
}

if (isset($this->conflictConstraintsPerPackage[$packageName])) {
foreach ($this->conflictConstraintsPerPackage[$packageName] as $conflictConstraint) {
if (CompilingMatcher::match($conflictConstraint, Constraint::OP_EQ, $package->getVersion())) {
$groupHashParts[] = 'conflict:' . (string) $conflictConstraint;
}
}
}

if (!$groupHashParts) {
continue;
}

$groupHash = implode('', $groupHashParts);
$identicalDefinitionsPerPackage[$packageName][$groupHash][$dependencyHash][] = $package;
$packageIdenticalDefinitionLookup[$package->id][$packageName] = ['groupHash' => $groupHash, 'dependencyHash' => $dependencyHash];
}
}
}

foreach ($identicalDefinitionsPerPackage as $constraintGroups) {
foreach ($constraintGroups as $constraintGroup) {
foreach ($constraintGroup as $packages) {

if (1 === \count($packages)) {
$this->keepPackage($packages[0], $identicalDefinitionsPerPackage, $packageIdenticalDefinitionLookup);
continue;
}



$literals = [];

foreach ($packages as $package) {
$literals[] = $package->id;
}

foreach ($this->policy->selectPreferredPackages($pool, $literals) as $preferredLiteral) {
$this->keepPackage($pool->literalToPackage($preferredLiteral), $identicalDefinitionsPerPackage, $packageIdenticalDefinitionLookup);
}
}
}
}
}

private function calculateDependencyHash(BasePackage $package): string
{
$hash = '';

$hashRelevantLinks = [
'requires' => $package->getRequires(),
'conflicts' => $package->getConflicts(),
'replaces' => $package->getReplaces(),
'provides' => $package->getProvides(),
];

foreach ($hashRelevantLinks as $key => $links) {
if (0 === \count($links)) {
continue;
}


$hash .= $key . ':';

$subhash = [];

foreach ($links as $link) {




$subhash[$link->getTarget()] = (string) $link->getConstraint();
}


ksort($subhash);

foreach ($subhash as $target => $constraint) {
$hash .= $target . '@' . $constraint;
}
}

return $hash;
}

private function markPackageForRemoval(int $id): void
{

if (isset($this->irremovablePackages[$id])) {
throw new \LogicException('Attempted removing a package which was previously marked irremovable');
}

$this->packagesToRemove[$id] = true;
}





private function keepPackage(BasePackage $package, array $identicalDefinitionsPerPackage, array $packageIdenticalDefinitionLookup): void
{

if (!isset($this->packagesToRemove[$package->id])) {
return;
}

unset($this->packagesToRemove[$package->id]);

if ($package instanceof AliasPackage) {


$this->keepPackage($package->getAliasOf(), $identicalDefinitionsPerPackage, $packageIdenticalDefinitionLookup);
}


foreach ($package->getNames(false) as $name) {
if (isset($packageIdenticalDefinitionLookup[$package->id][$name])) {
$packageGroupPointers = $packageIdenticalDefinitionLookup[$package->id][$name];
$packageGroup = $identicalDefinitionsPerPackage[$name][$packageGroupPointers['groupHash']][$packageGroupPointers['dependencyHash']];
foreach ($packageGroup as $pkg) {
if ($pkg instanceof AliasPackage && $pkg->getPrettyVersion() === VersionParser::DEFAULT_BRANCH_ALIAS) {
$pkg = $pkg->getAliasOf();
}
$this->removedVersionsByPackage[spl_object_hash($package)][$pkg->getVersion()] = $pkg->getPrettyVersion();
}
}
}

if (isset($this->aliasesPerPackage[$package->id])) {
foreach ($this->aliasesPerPackage[$package->id] as $aliasPackage) {
unset($this->packagesToRemove[$aliasPackage->id]);


foreach ($aliasPackage->getNames(false) as $name) {
if (isset($packageIdenticalDefinitionLookup[$aliasPackage->id][$name])) {
$packageGroupPointers = $packageIdenticalDefinitionLookup[$aliasPackage->id][$name];
$packageGroup = $identicalDefinitionsPerPackage[$name][$packageGroupPointers['groupHash']][$packageGroupPointers['dependencyHash']];
foreach ($packageGroup as $pkg) {
if ($pkg instanceof AliasPackage && $pkg->getPrettyVersion() === VersionParser::DEFAULT_BRANCH_ALIAS) {
$pkg = $pkg->getAliasOf();
}
$this->removedVersionsByPackage[spl_object_hash($aliasPackage)][$pkg->getVersion()] = $pkg->getPrettyVersion();
}
}
}
}
}
}






private function optimizeImpossiblePackagesAway(Request $request, Pool $pool): void
{
if (count($request->getLockedPackages()) === 0) {
return;
}

$packageIndex = [];

foreach ($pool->getPackages() as $package) {
$id = $package->id;


if (isset($this->irremovablePackages[$id])) {
continue;
}

if (isset($this->aliasesPerPackage[$id]) || $package instanceof AliasPackage) {
continue;
}

if ($request->isFixedPackage($package) || $request->isLockedPackage($package)) {
continue;
}

$packageIndex[$package->getName()][$package->id] = $package;
}

foreach ($request->getLockedPackages() as $package) {


$isUnusedPackage = true;
foreach ($package->getNames(false) as $packageName) {
if (isset($this->requireConstraintsPerPackage[$packageName])) {
$isUnusedPackage = false;
break;
}
}

if ($isUnusedPackage) {
continue;
}

foreach ($package->getRequires() as $link) {
$require = $link->getTarget();
if (!isset($packageIndex[$require])) {
continue;
}

$linkConstraint = $link->getConstraint();
foreach ($packageIndex[$require] as $id => $requiredPkg) {
if (false === CompilingMatcher::match($linkConstraint, Constraint::OP_EQ, $requiredPkg->getVersion())) {
$this->markPackageForRemoval($id);
unset($packageIndex[$require][$id]);
}
}
}
}
}








private function extractRequireConstraintsPerPackage(string $package, ConstraintInterface $constraint)
{
foreach ($this->expandDisjunctiveMultiConstraints($constraint) as $expanded) {
$this->requireConstraintsPerPackage[$package][(string) $expanded] = $expanded;
}
}








private function extractConflictConstraintsPerPackage(string $package, ConstraintInterface $constraint)
{
foreach ($this->expandDisjunctiveMultiConstraints($constraint) as $expanded) {
$this->conflictConstraintsPerPackage[$package][(string) $expanded] = $expanded;
}
}




private function expandDisjunctiveMultiConstraints(ConstraintInterface $constraint)
{
$constraint = Intervals::compactConstraint($constraint);

if ($constraint instanceof MultiConstraint && $constraint->isDisjunctive()) {


return $constraint->getConstraints();
}


return [$constraint];
}
}
