<?php declare(strict_types=1);











namespace Composer\DependencyResolver;

use Composer\Package\AliasPackage;
use Composer\Package\BasePackage;
use Composer\Package\Link;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositorySet;
use Composer\Package\Version\VersionParser;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;






abstract class Rule
{

public const RULE_ROOT_REQUIRE = 2; 
public const RULE_FIXED = 3; 
public const RULE_PACKAGE_CONFLICT = 6; 
public const RULE_PACKAGE_REQUIRES = 7; 
public const RULE_PACKAGE_SAME_NAME = 10; 
public const RULE_LEARNED = 12; 
public const RULE_PACKAGE_ALIAS = 13; 
public const RULE_PACKAGE_INVERSE_ALIAS = 14; 


private const BITFIELD_TYPE = 0;
private const BITFIELD_REASON = 8;
private const BITFIELD_DISABLED = 16;


protected $bitfield;

protected $request;




protected $reasonData;







public function __construct($reason, $reasonData)
{
$this->reasonData = $reasonData;

$this->bitfield = (0 << self::BITFIELD_DISABLED) |
($reason << self::BITFIELD_REASON) |
(255 << self::BITFIELD_TYPE);
}




abstract public function getLiterals(): array;




abstract public function getHash();

abstract public function __toString(): string;

abstract public function equals(Rule $rule): bool;




public function getReason(): int
{
return ($this->bitfield & (255 << self::BITFIELD_REASON)) >> self::BITFIELD_REASON;
}




public function getReasonData()
{
return $this->reasonData;
}

public function getRequiredPackage(): ?string
{
switch ($this->getReason()) {
case self::RULE_ROOT_REQUIRE:
return $this->getReasonData()['packageName'];
case self::RULE_FIXED:
return $this->getReasonData()['package']->getName();
case self::RULE_PACKAGE_REQUIRES:
return $this->getReasonData()->getTarget();
}

return null;
}




public function setType($type): void
{
$this->bitfield = ($this->bitfield & ~(255 << self::BITFIELD_TYPE)) | ((255 & $type) << self::BITFIELD_TYPE);
}

public function getType(): int
{
return ($this->bitfield & (255 << self::BITFIELD_TYPE)) >> self::BITFIELD_TYPE;
}

public function disable(): void
{
$this->bitfield = ($this->bitfield & ~(255 << self::BITFIELD_DISABLED)) | (1 << self::BITFIELD_DISABLED);
}

public function enable(): void
{
$this->bitfield &= ~(255 << self::BITFIELD_DISABLED);
}

public function isDisabled(): bool
{
return (bool) (($this->bitfield & (255 << self::BITFIELD_DISABLED)) >> self::BITFIELD_DISABLED);
}

public function isEnabled(): bool
{
return !(($this->bitfield & (255 << self::BITFIELD_DISABLED)) >> self::BITFIELD_DISABLED);
}

abstract public function isAssertion(): bool;

public function isCausedByLock(RepositorySet $repositorySet, Request $request, Pool $pool): bool
{
if ($this->getReason() === self::RULE_PACKAGE_REQUIRES) {
if (PlatformRepository::isPlatformPackage($this->getReasonData()->getTarget())) {
return false;
}
if ($request->getLockedRepository()) {
foreach ($request->getLockedRepository()->getPackages() as $package) {
if ($package->getName() === $this->getReasonData()->getTarget()) {
if ($pool->isUnacceptableFixedOrLockedPackage($package)) {
return true;
}
if (!$this->getReasonData()->getConstraint()->matches(new Constraint('=', $package->getVersion()))) {
return true;
}

if (!$request->isLockedPackage($package)) {
return true;
}
break;
}
}
}
}

if ($this->getReason() === self::RULE_ROOT_REQUIRE) {
if (PlatformRepository::isPlatformPackage($this->getReasonData()['packageName'])) {
return false;
}
if ($request->getLockedRepository()) {
foreach ($request->getLockedRepository()->getPackages() as $package) {
if ($package->getName() === $this->getReasonData()['packageName']) {
if ($pool->isUnacceptableFixedOrLockedPackage($package)) {
return true;
}
if (!$this->getReasonData()['constraint']->matches(new Constraint('=', $package->getVersion()))) {
return true;
}
break;
}
}
}
}

return false;
}




public function getSourcePackage(Pool $pool): BasePackage
{
$literals = $this->getLiterals();

switch ($this->getReason()) {
case self::RULE_PACKAGE_CONFLICT:
$package1 = $this->deduplicateDefaultBranchAlias($pool->literalToPackage($literals[0]));
$package2 = $this->deduplicateDefaultBranchAlias($pool->literalToPackage($literals[1]));

$reasonData = $this->getReasonData();

if ($reasonData->getSource() === $package1->getName()) {
[$package2, $package1] = [$package1, $package2];
}

return $package2;

case self::RULE_PACKAGE_REQUIRES:
$sourceLiteral = array_shift($literals);
$sourcePackage = $this->deduplicateDefaultBranchAlias($pool->literalToPackage($sourceLiteral));

return $sourcePackage;

default:
throw new \LogicException('Not implemented');
}
}





public function getPrettyString(RepositorySet $repositorySet, Request $request, Pool $pool, bool $isVerbose, array $installedMap = [], array $learnedPool = []): string
{
$literals = $this->getLiterals();

switch ($this->getReason()) {
case self::RULE_ROOT_REQUIRE:
$reasonData = $this->getReasonData();
$packageName = $reasonData['packageName'];
$constraint = $reasonData['constraint'];

$packages = $pool->whatProvides($packageName, $constraint);
if (!$packages) {
return 'No package found to satisfy root composer.json require '.$packageName.' '.$constraint->getPrettyString();
}

$packagesNonAlias = array_values(array_filter($packages, static function ($p): bool {
return !($p instanceof AliasPackage);
}));
if (count($packagesNonAlias) === 1) {
$package = $packagesNonAlias[0];
if ($request->isLockedPackage($package)) {
return $package->getPrettyName().' is locked to version '.$package->getPrettyVersion()." and an update of this package was not requested.";
}
}

return 'Root composer.json requires '.$packageName.' '.$constraint->getPrettyString().' -> satisfiable by '.$this->formatPackagesUnique($pool, $packages, $isVerbose, $constraint).'.';

case self::RULE_FIXED:
$package = $this->deduplicateDefaultBranchAlias($this->getReasonData()['package']);

if ($request->isLockedPackage($package)) {
return $package->getPrettyName().' is locked to version '.$package->getPrettyVersion().' and an update of this package was not requested.';
}

return $package->getPrettyName().' is present at version '.$package->getPrettyVersion() . ' and cannot be modified by Composer';

case self::RULE_PACKAGE_CONFLICT:
$package1 = $this->deduplicateDefaultBranchAlias($pool->literalToPackage($literals[0]));
$package2 = $this->deduplicateDefaultBranchAlias($pool->literalToPackage($literals[1]));

$conflictTarget = $package1->getPrettyString();
$reasonData = $this->getReasonData();


if ($reasonData->getSource() === $package1->getName()) {
[$package2, $package1] = [$package1, $package2];
$conflictTarget = $package1->getPrettyName().' '.$reasonData->getPrettyConstraint();
}



if ($reasonData->getTarget() !== $package1->getName()) {
$provideType = null;
$provided = null;
foreach ($package1->getProvides() as $provide) {
if ($provide->getTarget() === $reasonData->getTarget()) {
$provideType = 'provides';
$provided = $provide->getPrettyConstraint();
break;
}
}
foreach ($package1->getReplaces() as $replace) {
if ($replace->getTarget() === $reasonData->getTarget()) {
$provideType = 'replaces';
$provided = $replace->getPrettyConstraint();
break;
}
}
if (null !== $provideType) {
$conflictTarget = $reasonData->getTarget().' '.$reasonData->getPrettyConstraint().' ('.$package1->getPrettyString().' '.$provideType.' '.$reasonData->getTarget().' '.$provided.')';
}
}

return $package2->getPrettyString().' conflicts with '.$conflictTarget.'.';

case self::RULE_PACKAGE_REQUIRES:
$sourceLiteral = array_shift($literals);
$sourcePackage = $this->deduplicateDefaultBranchAlias($pool->literalToPackage($sourceLiteral));
$reasonData = $this->getReasonData();

$requires = [];
foreach ($literals as $literal) {
$requires[] = $pool->literalToPackage($literal);
}

$text = $reasonData->getPrettyString($sourcePackage);
if ($requires) {
$text .= ' -> satisfiable by ' . $this->formatPackagesUnique($pool, $requires, $isVerbose, $reasonData->getConstraint()) . '.';
} else {
$targetName = $reasonData->getTarget();

$reason = Problem::getMissingPackageReason($repositorySet, $request, $pool, $isVerbose, $targetName, $reasonData->getConstraint());

return $text . ' -> ' . $reason[1];
}

return $text;

case self::RULE_PACKAGE_SAME_NAME:
$packageNames = [];
foreach ($literals as $literal) {
$package = $pool->literalToPackage($literal);
$packageNames[$package->getName()] = true;
}
$replacedName = $this->getReasonData();

if (count($packageNames) > 1) {
$reason = null;

if (!isset($packageNames[$replacedName])) {
$reason = 'They '.(count($literals) === 2 ? 'both' : 'all').' replace '.$replacedName.' and thus cannot coexist.';
} else {
$replacerNames = $packageNames;
unset($replacerNames[$replacedName]);
$replacerNames = array_keys($replacerNames);

if (count($replacerNames) === 1) {
$reason = $replacerNames[0] . ' replaces ';
} else {
$reason = '['.implode(', ', $replacerNames).'] replace ';
}
$reason .= $replacedName.' and thus cannot coexist with it.';
}

$installedPackages = [];
$removablePackages = [];
foreach ($literals as $literal) {
if (isset($installedMap[abs($literal)])) {
$installedPackages[] = $pool->literalToPackage($literal);
} else {
$removablePackages[] = $pool->literalToPackage($literal);
}
}

if ($installedPackages && $removablePackages) {
return $this->formatPackagesUnique($pool, $removablePackages, $isVerbose, null, true).' cannot be installed as that would require removing '.$this->formatPackagesUnique($pool, $installedPackages, $isVerbose, null, true).'. '.$reason;
}

return 'Only one of these can be installed: '.$this->formatPackagesUnique($pool, $literals, $isVerbose, null, true).'. '.$reason;
}

return 'You can only install one version of a package, so only one of these can be installed: ' . $this->formatPackagesUnique($pool, $literals, $isVerbose, null, true) . '.';
case self::RULE_LEARNED:







$learnedString = ' (conflict analysis result)';

if (count($literals) === 1) {
$ruleText = $pool->literalToPrettyString($literals[0], $installedMap);
} else {
$groups = [];
foreach ($literals as $literal) {
$package = $pool->literalToPackage($literal);
if (isset($installedMap[$package->id])) {
$group = $literal > 0 ? 'keep' : 'remove';
} else {
$group = $literal > 0 ? 'install' : 'don\'t install';
}

$groups[$group][] = $this->deduplicateDefaultBranchAlias($package);
}
$ruleTexts = [];
foreach ($groups as $group => $packages) {
$ruleTexts[] = $group . (count($packages) > 1 ? ' one of' : '').' ' . $this->formatPackagesUnique($pool, $packages, $isVerbose);
}

$ruleText = implode(' | ', $ruleTexts);
}

return 'Conclusion: '.$ruleText.$learnedString;
case self::RULE_PACKAGE_ALIAS:
$aliasPackage = $pool->literalToPackage($literals[0]);


if ($aliasPackage->getVersion() === VersionParser::DEFAULT_BRANCH_ALIAS) {
return '';
}
$package = $this->deduplicateDefaultBranchAlias($pool->literalToPackage($literals[1]));

return $aliasPackage->getPrettyString() .' is an alias of '.$package->getPrettyString().' and thus requires it to be installed too.';
case self::RULE_PACKAGE_INVERSE_ALIAS:

$aliasPackage = $pool->literalToPackage($literals[1]);


if ($aliasPackage->getVersion() === VersionParser::DEFAULT_BRANCH_ALIAS) {
return '';
}
$package = $this->deduplicateDefaultBranchAlias($pool->literalToPackage($literals[0]));

return $aliasPackage->getPrettyString() .' is an alias of '.$package->getPrettyString().' and must be installed with it.';
default:
$ruleText = '';
foreach ($literals as $i => $literal) {
if ($i !== 0) {
$ruleText .= '|';
}
$ruleText .= $pool->literalToPrettyString($literal, $installedMap);
}

return '('.$ruleText.')';
}
}




protected function formatPackagesUnique(Pool $pool, array $packages, bool $isVerbose, ?ConstraintInterface $constraint = null, bool $useRemovedVersionGroup = false): string
{
foreach ($packages as $index => $package) {
if (!\is_object($package)) {
$packages[$index] = $pool->literalToPackage($package);
}
}

return Problem::getPackageList($packages, $isVerbose, $pool, $constraint, $useRemovedVersionGroup);
}

private function deduplicateDefaultBranchAlias(BasePackage $package): BasePackage
{
if ($package instanceof AliasPackage && $package->getPrettyVersion() === VersionParser::DEFAULT_BRANCH_ALIAS) {
$package = $package->getAliasOf();
}

return $package;
}
}
