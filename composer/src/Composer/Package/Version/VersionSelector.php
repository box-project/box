<?php declare(strict_types=1);











namespace Composer\Package\Version;

use Composer\Filter\PlatformRequirementFilter\IgnoreAllPlatformRequirementFilter;
use Composer\Filter\PlatformRequirementFilter\PlatformRequirementFilterFactory;
use Composer\Filter\PlatformRequirementFilter\PlatformRequirementFilterInterface;
use Composer\IO\IOInterface;
use Composer\Package\BasePackage;
use Composer\Package\AliasPackage;
use Composer\Package\PackageInterface;
use Composer\Composer;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Dumper\ArrayDumper;
use Composer\Pcre\Preg;
use Composer\Repository\RepositorySet;
use Composer\Repository\PlatformRepository;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;







class VersionSelector
{

private $repositorySet;


private $platformConstraints = [];


private $parser;




public function __construct(RepositorySet $repositorySet, ?PlatformRepository $platformRepo = null)
{
$this->repositorySet = $repositorySet;
if ($platformRepo) {
foreach ($platformRepo->getPackages() as $package) {
$this->platformConstraints[$package->getName()][] = new Constraint('==', $package->getVersion());
}
}
}











public function findBestCandidate(string $packageName, ?string $targetPackageVersion = null, string $preferredStability = 'stable', $platformRequirementFilter = null, int $repoSetFlags = 0, ?IOInterface $io = null, $showWarnings = true)
{
if (!isset(BasePackage::$stabilities[$preferredStability])) {

throw new \UnexpectedValueException('Expected a valid stability name as 3rd argument, got '.$preferredStability);
}

if (null === $platformRequirementFilter) {
$platformRequirementFilter = PlatformRequirementFilterFactory::ignoreNothing();
} elseif (!($platformRequirementFilter instanceof PlatformRequirementFilterInterface)) {
trigger_error('VersionSelector::findBestCandidate with ignored platform reqs as bool|array is deprecated since Composer 2.2, use an instance of PlatformRequirementFilterInterface instead.', E_USER_DEPRECATED);
$platformRequirementFilter = PlatformRequirementFilterFactory::fromBoolOrList($platformRequirementFilter);
}

$constraint = $targetPackageVersion ? $this->getParser()->parseConstraints($targetPackageVersion) : null;
$candidates = $this->repositorySet->findPackages(strtolower($packageName), $constraint, $repoSetFlags);

$minPriority = BasePackage::$stabilities[$preferredStability];
usort($candidates, static function (PackageInterface $a, PackageInterface $b) use ($minPriority) {
$aPriority = $a->getStabilityPriority();
$bPriority = $b->getStabilityPriority();



if ($minPriority < $aPriority && $bPriority < $aPriority) {
return 1;
}



if ($minPriority < $aPriority && $aPriority < $bPriority) {
return -1;
}



if ($minPriority >= $aPriority && $minPriority < $bPriority) {
return -1;
}


return version_compare($b->getVersion(), $a->getVersion());
});

if (count($this->platformConstraints) > 0 && !($platformRequirementFilter instanceof IgnoreAllPlatformRequirementFilter)) {

$alreadyWarnedNames = [];

$alreadySeenNames = [];

foreach ($candidates as $pkg) {
$reqs = $pkg->getRequires();
foreach ($reqs as $name => $link) {
if (!PlatformRepository::isPlatformPackage($name) || $platformRequirementFilter->isIgnored($name)) {
continue;
}
if (isset($this->platformConstraints[$name])) {
foreach ($this->platformConstraints[$name] as $providedConstraint) {
if ($link->getConstraint()->matches($providedConstraint)) {

continue 2;
}
}


$reason = 'is not satisfied by your platform';
} else {


$reason = 'is missing from your platform';
}

$isLatestVersion = !isset($alreadySeenNames[$pkg->getName()]);
$alreadySeenNames[$pkg->getName()] = true;
if ($io !== null && ($showWarnings === true || (is_callable($showWarnings) && $showWarnings($pkg)))) {
$isFirstWarning = !isset($alreadyWarnedNames[$pkg->getName()]);
$alreadyWarnedNames[$pkg->getName()] = true;
$latest = $isLatestVersion ? "'s latest version" : '';
$io->writeError(
'<warning>Cannot use '.$pkg->getPrettyName().$latest.' '.$pkg->getPrettyVersion().' as it '.$link->getDescription().' '.$link->getTarget().' '.$link->getPrettyConstraint().' which '.$reason.'.</>',
true,
$isFirstWarning ? IOInterface::NORMAL : IOInterface::VERBOSE
);
}


continue 2;
}

$package = $pkg;
break;
}
} else {
$package = count($candidates) > 0 ? $candidates[0] : null;
}

if (!isset($package)) {
return false;
}


if ($package instanceof AliasPackage && $package->getVersion() === VersionParser::DEFAULT_BRANCH_ALIAS) {
$package = $package->getAliasOf();
}

return $package;
}













public function findRecommendedRequireVersion(PackageInterface $package): string
{


if (0 === strpos($package->getName(), 'ext-')) {
$phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
$extVersion = implode('.', array_slice(explode('.', $package->getVersion()), 0, 3));
if ($phpVersion === $extVersion) {
return '*';
}
}

$version = $package->getVersion();
if (!$package->isDev()) {
return $this->transformVersion($version, $package->getPrettyVersion(), $package->getStability());
}

$loader = new ArrayLoader($this->getParser());
$dumper = new ArrayDumper();
$extra = $loader->getBranchAlias($dumper->dump($package));
if ($extra && $extra !== VersionParser::DEFAULT_BRANCH_ALIAS) {
$extra = Preg::replace('{^(\d+\.\d+\.\d+)(\.9999999)-dev$}', '$1.0', $extra, -1, $count);
if ($count > 0) {
$extra = str_replace('.9999999', '.0', $extra);

return $this->transformVersion($extra, $extra, 'dev');
}
}

return $package->getPrettyVersion();
}

private function transformVersion(string $version, string $prettyVersion, string $stability): string
{


$semanticVersionParts = explode('.', $version);


if (count($semanticVersionParts) === 4 && Preg::isMatch('{^0\D?}', $semanticVersionParts[3])) {

if ($semanticVersionParts[0] === '0') {
unset($semanticVersionParts[3]);
} else {
unset($semanticVersionParts[2], $semanticVersionParts[3]);
}
$version = implode('.', $semanticVersionParts);
} else {
return $prettyVersion;
}


if ($stability !== 'stable') {
$version .= '@'.$stability;
}


return '^' . $version;
}

private function getParser(): VersionParser
{
if ($this->parser === null) {
$this->parser = new VersionParser();
}

return $this->parser;
}
}
