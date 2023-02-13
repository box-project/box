<?php declare(strict_types=1);











namespace Composer\DependencyResolver;

use Composer\Filter\PlatformRequirementFilter\IgnoreListPlatformRequirementFilter;
use Composer\Filter\PlatformRequirementFilter\PlatformRequirementFilterFactory;
use Composer\Filter\PlatformRequirementFilter\PlatformRequirementFilterInterface;
use Composer\Package\BasePackage;
use Composer\Package\AliasPackage;





class RuleSetGenerator
{

protected $policy;

protected $pool;

protected $rules;

protected $addedMap = [];

protected $addedPackagesByNames = [];

public function __construct(PolicyInterface $policy, Pool $pool)
{
$this->policy = $policy;
$this->pool = $pool;
$this->rules = new RuleSet;
}















protected function createRequireRule(BasePackage $package, array $providers, $reason, $reasonData = null): ?Rule
{
$literals = [-$package->id];

foreach ($providers as $provider) {

if ($provider === $package) {
return null;
}
$literals[] = $provider->id;
}

return new GenericRule($literals, $reason, $reasonData);
}















protected function createInstallOneOfRule(array $packages, $reason, $reasonData): Rule
{
$literals = [];
foreach ($packages as $package) {
$literals[] = $package->id;
}

return new GenericRule($literals, $reason, $reasonData);
}















protected function createRule2Literals(BasePackage $issuer, BasePackage $provider, $reason, $reasonData = null): ?Rule
{

if ($issuer === $provider) {
return null;
}

return new Rule2Literals(-$issuer->id, -$provider->id, $reason, $reasonData);
}








protected function createMultiConflictRule(array $packages, $reason, $reasonData): Rule
{
$literals = [];
foreach ($packages as $package) {
$literals[] = -$package->id;
}

if (\count($literals) === 2) {
return new Rule2Literals($literals[0], $literals[1], $reason, $reasonData);
}

return new MultiConflictRule($literals, $reason, $reasonData);
}










private function addRule($type, ?Rule $newRule = null): void
{
if (!$newRule) {
return;
}

$this->rules->add($newRule, $type);
}

protected function addRulesForPackage(BasePackage $package, PlatformRequirementFilterInterface $platformRequirementFilter): void
{

$workQueue = new \SplQueue;
$workQueue->enqueue($package);

while (!$workQueue->isEmpty()) {
$package = $workQueue->dequeue();
if (isset($this->addedMap[$package->id])) {
continue;
}

$this->addedMap[$package->id] = $package;

if (!$package instanceof AliasPackage) {
foreach ($package->getNames(false) as $name) {
$this->addedPackagesByNames[$name][] = $package;
}
} else {
$workQueue->enqueue($package->getAliasOf());
$this->addRule(RuleSet::TYPE_PACKAGE, $this->createRequireRule($package, [$package->getAliasOf()], Rule::RULE_PACKAGE_ALIAS, $package));


$this->addRule(RuleSet::TYPE_PACKAGE, $this->createRequireRule($package->getAliasOf(), [$package], Rule::RULE_PACKAGE_INVERSE_ALIAS, $package->getAliasOf()));



if (!$package->hasSelfVersionRequires()) {
continue;
}
}

foreach ($package->getRequires() as $link) {
$constraint = $link->getConstraint();
if ($platformRequirementFilter->isIgnored($link->getTarget())) {
continue;
} elseif ($platformRequirementFilter instanceof IgnoreListPlatformRequirementFilter) {
$constraint = $platformRequirementFilter->filterConstraint($link->getTarget(), $constraint);
}

$possibleRequires = $this->pool->whatProvides($link->getTarget(), $constraint);

$this->addRule(RuleSet::TYPE_PACKAGE, $this->createRequireRule($package, $possibleRequires, Rule::RULE_PACKAGE_REQUIRES, $link));

foreach ($possibleRequires as $require) {
$workQueue->enqueue($require);
}
}
}
}

protected function addConflictRules(PlatformRequirementFilterInterface $platformRequirementFilter): void
{

foreach ($this->addedMap as $package) {
foreach ($package->getConflicts() as $link) {

if (!isset($this->addedPackagesByNames[$link->getTarget()])) {
continue;
}

$constraint = $link->getConstraint();
if ($platformRequirementFilter->isIgnored($link->getTarget())) {
continue;
} elseif ($platformRequirementFilter instanceof IgnoreListPlatformRequirementFilter) {
$constraint = $platformRequirementFilter->filterConstraint($link->getTarget(), $constraint, false);
}

$conflicts = $this->pool->whatProvides($link->getTarget(), $constraint);

foreach ($conflicts as $conflict) {



if (!$conflict instanceof AliasPackage || $conflict->getName() === $link->getTarget()) {
$this->addRule(RuleSet::TYPE_PACKAGE, $this->createRule2Literals($package, $conflict, Rule::RULE_PACKAGE_CONFLICT, $link));
}
}
}
}

foreach ($this->addedPackagesByNames as $name => $packages) {
if (\count($packages) > 1) {
$reason = Rule::RULE_PACKAGE_SAME_NAME;
$this->addRule(RuleSet::TYPE_PACKAGE, $this->createMultiConflictRule($packages, $reason, $name));
}
}
}

protected function addRulesForRequest(Request $request, PlatformRequirementFilterInterface $platformRequirementFilter): void
{
foreach ($request->getFixedPackages() as $package) {
if ($package->id === -1) {

if ($this->pool->isUnacceptableFixedOrLockedPackage($package)) {
continue;
}


throw new \LogicException("Fixed package ".$package->getPrettyString()." was not added to solver pool.");
}

$this->addRulesForPackage($package, $platformRequirementFilter);

$rule = $this->createInstallOneOfRule([$package], Rule::RULE_FIXED, [
'package' => $package,
]);
$this->addRule(RuleSet::TYPE_REQUEST, $rule);
}

foreach ($request->getRequires() as $packageName => $constraint) {
if ($platformRequirementFilter->isIgnored($packageName)) {
continue;
} elseif ($platformRequirementFilter instanceof IgnoreListPlatformRequirementFilter) {
$constraint = $platformRequirementFilter->filterConstraint($packageName, $constraint);
}

$packages = $this->pool->whatProvides($packageName, $constraint);
if ($packages) {
foreach ($packages as $package) {
$this->addRulesForPackage($package, $platformRequirementFilter);
}

$rule = $this->createInstallOneOfRule($packages, Rule::RULE_ROOT_REQUIRE, [
'packageName' => $packageName,
'constraint' => $constraint,
]);
$this->addRule(RuleSet::TYPE_REQUEST, $rule);
}
}
}

protected function addRulesForRootAliases(PlatformRequirementFilterInterface $platformRequirementFilter): void
{
foreach ($this->pool->getPackages() as $package) {



if (!isset($this->addedMap[$package->id]) &&
$package instanceof AliasPackage &&
($package->isRootPackageAlias() || isset($this->addedMap[$package->getAliasOf()->id]))
) {
$this->addRulesForPackage($package, $platformRequirementFilter);
}
}
}

public function getRulesFor(Request $request, ?PlatformRequirementFilterInterface $platformRequirementFilter = null): RuleSet
{
$platformRequirementFilter = $platformRequirementFilter ?: PlatformRequirementFilterFactory::ignoreNothing();

$this->addRulesForRequest($request, $platformRequirementFilter);

$this->addRulesForRootAliases($platformRequirementFilter);

$this->addConflictRules($platformRequirementFilter);


$this->addedMap = $this->addedPackagesByNames = [];

$rules = $this->rules;

$this->rules = new RuleSet;

return $rules;
}
}
