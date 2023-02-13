<?php declare(strict_types=1);











namespace Composer\Package;




class RootAliasPackage extends CompleteAliasPackage implements RootPackageInterface
{

protected $aliasOf;








public function __construct(RootPackage $aliasOf, string $version, string $prettyVersion)
{
parent::__construct($aliasOf, $version, $prettyVersion);
}




public function getAliasOf()
{
return $this->aliasOf;
}




public function getAliases(): array
{
return $this->aliasOf->getAliases();
}




public function getMinimumStability(): string
{
return $this->aliasOf->getMinimumStability();
}




public function getStabilityFlags(): array
{
return $this->aliasOf->getStabilityFlags();
}




public function getReferences(): array
{
return $this->aliasOf->getReferences();
}




public function getPreferStable(): bool
{
return $this->aliasOf->getPreferStable();
}




public function getConfig(): array
{
return $this->aliasOf->getConfig();
}




public function setRequires(array $requires): void
{
$this->requires = $this->replaceSelfVersionDependencies($requires, Link::TYPE_REQUIRE);

$this->aliasOf->setRequires($requires);
}




public function setDevRequires(array $devRequires): void
{
$this->devRequires = $this->replaceSelfVersionDependencies($devRequires, Link::TYPE_DEV_REQUIRE);

$this->aliasOf->setDevRequires($devRequires);
}




public function setConflicts(array $conflicts): void
{
$this->conflicts = $this->replaceSelfVersionDependencies($conflicts, Link::TYPE_CONFLICT);
$this->aliasOf->setConflicts($conflicts);
}




public function setProvides(array $provides): void
{
$this->provides = $this->replaceSelfVersionDependencies($provides, Link::TYPE_PROVIDE);
$this->aliasOf->setProvides($provides);
}




public function setReplaces(array $replaces): void
{
$this->replaces = $this->replaceSelfVersionDependencies($replaces, Link::TYPE_REPLACE);
$this->aliasOf->setReplaces($replaces);
}




public function setAutoload(array $autoload): void
{
$this->aliasOf->setAutoload($autoload);
}




public function setDevAutoload(array $devAutoload): void
{
$this->aliasOf->setDevAutoload($devAutoload);
}




public function setStabilityFlags(array $stabilityFlags): void
{
$this->aliasOf->setStabilityFlags($stabilityFlags);
}




public function setMinimumStability(string $minimumStability): void
{
$this->aliasOf->setMinimumStability($minimumStability);
}




public function setPreferStable(bool $preferStable): void
{
$this->aliasOf->setPreferStable($preferStable);
}




public function setConfig(array $config): void
{
$this->aliasOf->setConfig($config);
}




public function setReferences(array $references): void
{
$this->aliasOf->setReferences($references);
}




public function setAliases(array $aliases): void
{
$this->aliasOf->setAliases($aliases);
}




public function setSuggests(array $suggests): void
{
$this->aliasOf->setSuggests($suggests);
}




public function setExtra(array $extra): void
{
$this->aliasOf->setExtra($extra);
}

public function __clone()
{
parent::__clone();
$this->aliasOf = clone $this->aliasOf;
}
}
