<?php declare(strict_types=1);











namespace Composer\Package;






class CompletePackage extends Package implements CompletePackageInterface
{

protected $repositories = [];

protected $license = [];

protected $keywords = [];

protected $authors = [];

protected $description = null;

protected $homepage = null;

protected $scripts = [];

protected $support = [];

protected $funding = [];

protected $abandoned = false;

protected $archiveName = null;

protected $archiveExcludes = [];




public function setScripts(array $scripts): void
{
$this->scripts = $scripts;
}




public function getScripts(): array
{
return $this->scripts;
}




public function setRepositories(array $repositories): void
{
$this->repositories = $repositories;
}




public function getRepositories(): array
{
return $this->repositories;
}




public function setLicense(array $license): void
{
$this->license = $license;
}




public function getLicense(): array
{
return $this->license;
}




public function setKeywords(array $keywords): void
{
$this->keywords = $keywords;
}




public function getKeywords(): array
{
return $this->keywords;
}




public function setAuthors(array $authors): void
{
$this->authors = $authors;
}




public function getAuthors(): array
{
return $this->authors;
}




public function setDescription(?string $description): void
{
$this->description = $description;
}




public function getDescription(): ?string
{
return $this->description;
}




public function setHomepage(?string $homepage): void
{
$this->homepage = $homepage;
}




public function getHomepage(): ?string
{
return $this->homepage;
}




public function setSupport(array $support): void
{
$this->support = $support;
}




public function getSupport(): array
{
return $this->support;
}




public function setFunding(array $funding): void
{
$this->funding = $funding;
}




public function getFunding(): array
{
return $this->funding;
}




public function isAbandoned(): bool
{
return (bool) $this->abandoned;
}




public function setAbandoned($abandoned): void
{
$this->abandoned = $abandoned;
}




public function getReplacementPackage(): ?string
{
return \is_string($this->abandoned) ? $this->abandoned : null;
}




public function setArchiveName(?string $name): void
{
$this->archiveName = $name;
}




public function getArchiveName(): ?string
{
return $this->archiveName;
}




public function setArchiveExcludes(array $excludes): void
{
$this->archiveExcludes = $excludes;
}




public function getArchiveExcludes(): array
{
return $this->archiveExcludes;
}
}
