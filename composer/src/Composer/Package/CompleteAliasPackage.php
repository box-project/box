<?php declare(strict_types=1);











namespace Composer\Package;




class CompleteAliasPackage extends AliasPackage implements CompletePackageInterface
{

protected $aliasOf;








public function __construct(CompletePackage $aliasOf, string $version, string $prettyVersion)
{
parent::__construct($aliasOf, $version, $prettyVersion);
}




public function getAliasOf()
{
return $this->aliasOf;
}

public function getScripts(): array
{
return $this->aliasOf->getScripts();
}

public function setScripts(array $scripts): void
{
$this->aliasOf->setScripts($scripts);
}

public function getRepositories(): array
{
return $this->aliasOf->getRepositories();
}

public function setRepositories(array $repositories): void
{
$this->aliasOf->setRepositories($repositories);
}

public function getLicense(): array
{
return $this->aliasOf->getLicense();
}

public function setLicense(array $license): void
{
$this->aliasOf->setLicense($license);
}

public function getKeywords(): array
{
return $this->aliasOf->getKeywords();
}

public function setKeywords(array $keywords): void
{
$this->aliasOf->setKeywords($keywords);
}

public function getDescription(): ?string
{
return $this->aliasOf->getDescription();
}

public function setDescription(?string $description): void
{
$this->aliasOf->setDescription($description);
}

public function getHomepage(): ?string
{
return $this->aliasOf->getHomepage();
}

public function setHomepage(?string $homepage): void
{
$this->aliasOf->setHomepage($homepage);
}

public function getAuthors(): array
{
return $this->aliasOf->getAuthors();
}

public function setAuthors(array $authors): void
{
$this->aliasOf->setAuthors($authors);
}

public function getSupport(): array
{
return $this->aliasOf->getSupport();
}

public function setSupport(array $support): void
{
$this->aliasOf->setSupport($support);
}

public function getFunding(): array
{
return $this->aliasOf->getFunding();
}

public function setFunding(array $funding): void
{
$this->aliasOf->setFunding($funding);
}

public function isAbandoned(): bool
{
return $this->aliasOf->isAbandoned();
}

public function getReplacementPackage(): ?string
{
return $this->aliasOf->getReplacementPackage();
}

public function setAbandoned($abandoned): void
{
$this->aliasOf->setAbandoned($abandoned);
}

public function getArchiveName(): ?string
{
return $this->aliasOf->getArchiveName();
}

public function setArchiveName(?string $name): void
{
$this->aliasOf->setArchiveName($name);
}

public function getArchiveExcludes(): array
{
return $this->aliasOf->getArchiveExcludes();
}

public function setArchiveExcludes(array $excludes): void
{
$this->aliasOf->setArchiveExcludes($excludes);
}
}
