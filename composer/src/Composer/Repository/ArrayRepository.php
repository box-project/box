<?php declare(strict_types=1);











namespace Composer\Repository;

use Composer\Package\AliasPackage;
use Composer\Package\BasePackage;
use Composer\Package\CompleteAliasPackage;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Composer\Package\CompletePackageInterface;
use Composer\Package\Version\VersionParser;
use Composer\Package\Version\StabilityFilter;
use Composer\Pcre\Preg;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Constraint\Constraint;






class ArrayRepository implements RepositoryInterface
{

protected $packages = null;




protected $packageMap = null;




public function __construct(array $packages = [])
{
foreach ($packages as $package) {
$this->addPackage($package);
}
}

public function getRepoName()
{
return 'array repo (defining '.$this->count().' package'.($this->count() > 1 ? 's' : '').')';
}




public function loadPackages(array $packageNameMap, array $acceptableStabilities, array $stabilityFlags, array $alreadyLoaded = [])
{
$packages = $this->getPackages();

$result = [];
$namesFound = [];
foreach ($packages as $package) {
if (array_key_exists($package->getName(), $packageNameMap)) {
if (
(!$packageNameMap[$package->getName()] || $packageNameMap[$package->getName()]->matches(new Constraint('==', $package->getVersion())))
&& StabilityFilter::isPackageAcceptable($acceptableStabilities, $stabilityFlags, $package->getNames(), $package->getStability())
&& !isset($alreadyLoaded[$package->getName()][$package->getVersion()])
) {

$result[spl_object_hash($package)] = $package;

if ($package instanceof AliasPackage && !isset($result[spl_object_hash($package->getAliasOf())])) {
$result[spl_object_hash($package->getAliasOf())] = $package->getAliasOf();
}
}

$namesFound[$package->getName()] = true;
}
}


foreach ($packages as $package) {
if ($package instanceof AliasPackage) {
if (isset($result[spl_object_hash($package->getAliasOf())])) {
$result[spl_object_hash($package)] = $package;
}
}
}

return ['namesFound' => array_keys($namesFound), 'packages' => $result];
}




public function findPackage(string $name, $constraint)
{
$name = strtolower($name);

if (!$constraint instanceof ConstraintInterface) {
$versionParser = new VersionParser();
$constraint = $versionParser->parseConstraints($constraint);
}

foreach ($this->getPackages() as $package) {
if ($name === $package->getName()) {
$pkgConstraint = new Constraint('==', $package->getVersion());
if ($constraint->matches($pkgConstraint)) {
return $package;
}
}
}

return null;
}




public function findPackages(string $name, $constraint = null)
{

$name = strtolower($name);
$packages = [];

if (null !== $constraint && !$constraint instanceof ConstraintInterface) {
$versionParser = new VersionParser();
$constraint = $versionParser->parseConstraints($constraint);
}

foreach ($this->getPackages() as $package) {
if ($name === $package->getName()) {
if (null === $constraint || $constraint->matches(new Constraint('==', $package->getVersion()))) {
$packages[] = $package;
}
}
}

return $packages;
}




public function search(string $query, int $mode = 0, ?string $type = null)
{
if ($mode === self::SEARCH_FULLTEXT) {
$regex = '{(?:'.implode('|', Preg::split('{\s+}', preg_quote($query))).')}i';
} else {

$regex = '{(?:'.implode('|', Preg::split('{\s+}', $query)).')}i';
}

$matches = [];
foreach ($this->getPackages() as $package) {
$name = $package->getName();
if ($mode === self::SEARCH_VENDOR) {
[$name] = explode('/', $name);
}
if (isset($matches[$name])) {
continue;
}
if (null !== $type && $package->getType() !== $type) {
continue;
}

if (Preg::isMatch($regex, $name)
|| ($mode === self::SEARCH_FULLTEXT && $package instanceof CompletePackageInterface && Preg::isMatch($regex, implode(' ', (array) $package->getKeywords()) . ' ' . $package->getDescription()))
) {
if ($mode === self::SEARCH_VENDOR) {
$matches[$name] = [
'name' => $name,
'description' => null,
];
} else {
$matches[$name] = [
'name' => $package->getPrettyName(),
'description' => $package instanceof CompletePackageInterface ? $package->getDescription() : null,
];

if ($package instanceof CompletePackageInterface && $package->isAbandoned()) {
$matches[$name]['abandoned'] = $package->getReplacementPackage() ?: true;
}
}
}
}

return array_values($matches);
}




public function hasPackage(PackageInterface $package)
{
if ($this->packageMap === null) {
$this->packageMap = [];
foreach ($this->getPackages() as $repoPackage) {
$this->packageMap[$repoPackage->getUniqueName()] = $repoPackage;
}
}

return isset($this->packageMap[$package->getUniqueName()]);
}






public function addPackage(PackageInterface $package)
{
if (!$package instanceof BasePackage) {
throw new \InvalidArgumentException('Only subclasses of BasePackage are supported');
}
if (null === $this->packages) {
$this->initialize();
}
$package->setRepository($this);
$this->packages[] = $package;

if ($package instanceof AliasPackage) {
$aliasedPackage = $package->getAliasOf();
if (null === $aliasedPackage->getRepository()) {
$this->addPackage($aliasedPackage);
}
}


$this->packageMap = null;
}




public function getProviders(string $packageName)
{
$result = [];

foreach ($this->getPackages() as $candidate) {
if (isset($result[$candidate->getName()])) {
continue;
}
foreach ($candidate->getProvides() as $link) {
if ($packageName === $link->getTarget()) {
$result[$candidate->getName()] = [
'name' => $candidate->getName(),
'description' => $candidate instanceof CompletePackageInterface ? $candidate->getDescription() : null,
'type' => $candidate->getType(),
];
continue 2;
}
}
}

return $result;
}




protected function createAliasPackage(BasePackage $package, string $alias, string $prettyAlias)
{
while ($package instanceof AliasPackage) {
$package = $package->getAliasOf();
}

if ($package instanceof CompletePackage) {
return new CompleteAliasPackage($package, $alias, $prettyAlias);
}

return new AliasPackage($package, $alias, $prettyAlias);
}








public function removePackage(PackageInterface $package)
{
$packageId = $package->getUniqueName();

foreach ($this->getPackages() as $key => $repoPackage) {
if ($packageId === $repoPackage->getUniqueName()) {
array_splice($this->packages, $key, 1);


$this->packageMap = null;

return;
}
}
}




public function getPackages()
{
if (null === $this->packages) {
$this->initialize();
}

if (null === $this->packages) {
throw new \LogicException('initialize failed to initialize the packages array');
}

return $this->packages;
}






public function count(): int
{
if (null === $this->packages) {
$this->initialize();
}

return count($this->packages);
}






protected function initialize()
{
$this->packages = [];
}
}
