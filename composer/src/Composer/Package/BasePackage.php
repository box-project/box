<?php declare(strict_types=1);











namespace Composer\Package;

use Composer\Repository\RepositoryInterface;
use Composer\Repository\PlatformRepository;






abstract class BasePackage implements PackageInterface
{




public static $supportedLinkTypes = [
'require' => ['description' => 'requires', 'method' => Link::TYPE_REQUIRE],
'conflict' => ['description' => 'conflicts', 'method' => Link::TYPE_CONFLICT],
'provide' => ['description' => 'provides', 'method' => Link::TYPE_PROVIDE],
'replace' => ['description' => 'replaces', 'method' => Link::TYPE_REPLACE],
'require-dev' => ['description' => 'requires (for development)', 'method' => Link::TYPE_DEV_REQUIRE],
];

public const STABILITY_STABLE = 0;
public const STABILITY_RC = 5;
public const STABILITY_BETA = 10;
public const STABILITY_ALPHA = 15;
public const STABILITY_DEV = 20;


public static $stabilities = [
'stable' => self::STABILITY_STABLE,
'RC' => self::STABILITY_RC,
'beta' => self::STABILITY_BETA,
'alpha' => self::STABILITY_ALPHA,
'dev' => self::STABILITY_DEV,
];






public $id;

protected $name;

protected $prettyName;

protected $repository = null;






public function __construct(string $name)
{
$this->prettyName = $name;
$this->name = strtolower($name);
$this->id = -1;
}




public function getName(): string
{
return $this->name;
}




public function getPrettyName(): string
{
return $this->prettyName;
}




public function getNames($provides = true): array
{
$names = [
$this->getName() => true,
];

if ($provides) {
foreach ($this->getProvides() as $link) {
$names[$link->getTarget()] = true;
}
}

foreach ($this->getReplaces() as $link) {
$names[$link->getTarget()] = true;
}

return array_keys($names);
}




public function setId(int $id): void
{
$this->id = $id;
}




public function getId(): int
{
return $this->id;
}




public function setRepository(RepositoryInterface $repository): void
{
if ($this->repository && $repository !== $this->repository) {
throw new \LogicException(sprintf(
'Package "%s" cannot be added to repository "%s" as it is already in repository "%s".',
$this->getPrettyName(),
$repository->getRepoName(),
$this->repository->getRepoName()
));
}
$this->repository = $repository;
}




public function getRepository(): ?RepositoryInterface
{
return $this->repository;
}




public function isPlatform(): bool
{
return $this->getRepository() instanceof PlatformRepository;
}




public function getUniqueName(): string
{
return $this->getName().'-'.$this->getVersion();
}

public function equals(PackageInterface $package): bool
{
$self = $this;
if ($this instanceof AliasPackage) {
$self = $this->getAliasOf();
}
if ($package instanceof AliasPackage) {
$package = $package->getAliasOf();
}

return $package === $self;
}




public function __toString(): string
{
return $this->getUniqueName();
}

public function getPrettyString(): string
{
return $this->getPrettyName().' '.$this->getPrettyVersion();
}




public function getFullPrettyVersion(bool $truncate = true, int $displayMode = PackageInterface::DISPLAY_SOURCE_REF_IF_DEV): string
{
if ($displayMode === PackageInterface::DISPLAY_SOURCE_REF_IF_DEV &&
(!$this->isDev() || !\in_array($this->getSourceType(), ['hg', 'git']))
) {
return $this->getPrettyVersion();
}

switch ($displayMode) {
case PackageInterface::DISPLAY_SOURCE_REF_IF_DEV:
case PackageInterface::DISPLAY_SOURCE_REF:
$reference = $this->getSourceReference();
break;
case PackageInterface::DISPLAY_DIST_REF:
$reference = $this->getDistReference();
break;
default:
throw new \UnexpectedValueException('Display mode '.$displayMode.' is not supported');
}

if (null === $reference) {
return $this->getPrettyVersion();
}


if ($truncate && \strlen($reference) === 40 && $this->getSourceType() !== 'svn') {
return $this->getPrettyVersion() . ' ' . substr($reference, 0, 7);
}

return $this->getPrettyVersion() . ' ' . $reference;
}




public function getStabilityPriority(): int
{
return self::$stabilities[$this->getStability()];
}

public function __clone()
{
$this->repository = null;
$this->id = -1;
}







public static function packageNameToRegexp(string $allowPattern, string $wrap = '{^%s$}i'): string
{
$cleanedAllowPattern = str_replace('\\*', '.*', preg_quote($allowPattern));

return sprintf($wrap, $cleanedAllowPattern);
}








public static function packageNamesToRegexp(array $packageNames, string $wrap = '{^(?:%s)$}iD'): string
{
$packageNames = array_map(
static function ($packageName): string {
return BasePackage::packageNameToRegexp($packageName, '%s');
},
$packageNames
);

return sprintf($wrap, implode('|', $packageNames));
}
}
