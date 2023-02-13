<?php declare(strict_types=1);











namespace Composer\Package;






class RootPackage extends CompletePackage implements RootPackageInterface
{
public const DEFAULT_PRETTY_VERSION = '1.0.0+no-version-set';


protected $minimumStability = 'stable';

protected $preferStable = false;

protected $stabilityFlags = [];

protected $config = [];

protected $references = [];

protected $aliases = [];




public function setMinimumStability(string $minimumStability): void
{
$this->minimumStability = $minimumStability;
}




public function getMinimumStability(): string
{
return $this->minimumStability;
}




public function setStabilityFlags(array $stabilityFlags): void
{
$this->stabilityFlags = $stabilityFlags;
}




public function getStabilityFlags(): array
{
return $this->stabilityFlags;
}




public function setPreferStable(bool $preferStable): void
{
$this->preferStable = $preferStable;
}




public function getPreferStable(): bool
{
return $this->preferStable;
}




public function setConfig(array $config): void
{
$this->config = $config;
}




public function getConfig(): array
{
return $this->config;
}




public function setReferences(array $references): void
{
$this->references = $references;
}




public function getReferences(): array
{
return $this->references;
}




public function setAliases(array $aliases): void
{
$this->aliases = $aliases;
}




public function getAliases(): array
{
return $this->aliases;
}
}
