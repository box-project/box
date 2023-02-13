<?php declare(strict_types=1);











namespace Composer\Package;

use Composer\Semver\Constraint\Constraint;
use Composer\Package\Version\VersionParser;




class AliasPackage extends BasePackage
{

protected $version;

protected $prettyVersion;

protected $dev;

protected $rootPackageAlias = false;




protected $stability;

protected $hasSelfVersionRequires = false;


protected $aliasOf;

protected $requires;

protected $devRequires;

protected $conflicts;

protected $provides;

protected $replaces;








public function __construct(BasePackage $aliasOf, string $version, string $prettyVersion)
{
parent::__construct($aliasOf->getName());

$this->version = $version;
$this->prettyVersion = $prettyVersion;
$this->aliasOf = $aliasOf;
$this->stability = VersionParser::parseStability($version);
$this->dev = $this->stability === 'dev';

foreach (Link::$TYPES as $type) {
$links = $aliasOf->{'get' . ucfirst($type)}();
$this->{$type} = $this->replaceSelfVersionDependencies($links, $type);
}
}




public function getAliasOf()
{
return $this->aliasOf;
}




public function getVersion(): string
{
return $this->version;
}




public function getStability(): string
{
return $this->stability;
}




public function getPrettyVersion(): string
{
return $this->prettyVersion;
}




public function isDev(): bool
{
return $this->dev;
}




public function getRequires(): array
{
return $this->requires;
}





public function getConflicts(): array
{
return $this->conflicts;
}





public function getProvides(): array
{
return $this->provides;
}





public function getReplaces(): array
{
return $this->replaces;
}




public function getDevRequires(): array
{
return $this->devRequires;
}






public function setRootPackageAlias(bool $value): void
{
$this->rootPackageAlias = $value;
}




public function isRootPackageAlias(): bool
{
return $this->rootPackageAlias;
}







protected function replaceSelfVersionDependencies(array $links, $linkType): array
{

$prettyVersion = $this->prettyVersion;
if ($prettyVersion === VersionParser::DEFAULT_BRANCH_ALIAS) {
$prettyVersion = $this->aliasOf->getPrettyVersion();
}

if (\in_array($linkType, [Link::TYPE_CONFLICT, Link::TYPE_PROVIDE, Link::TYPE_REPLACE], true)) {
$newLinks = [];
foreach ($links as $link) {

if ('self.version' === $link->getPrettyConstraint()) {
$newLinks[] = new Link($link->getSource(), $link->getTarget(), $constraint = new Constraint('=', $this->version), $linkType, $prettyVersion);
$constraint->setPrettyString($prettyVersion);
}
}
$links = array_merge($links, $newLinks);
} else {
foreach ($links as $index => $link) {
if ('self.version' === $link->getPrettyConstraint()) {
if ($linkType === Link::TYPE_REQUIRE) {
$this->hasSelfVersionRequires = true;
}
$links[$index] = new Link($link->getSource(), $link->getTarget(), $constraint = new Constraint('=', $this->version), $linkType, $prettyVersion);
$constraint->setPrettyString($prettyVersion);
}
}
}

return $links;
}

public function hasSelfVersionRequires(): bool
{
return $this->hasSelfVersionRequires;
}

public function __toString(): string
{
return parent::__toString().' ('.($this->rootPackageAlias ? 'root ' : ''). 'alias of '.$this->aliasOf->getVersion().')';
}





public function getType(): string
{
return $this->aliasOf->getType();
}

public function getTargetDir(): ?string
{
return $this->aliasOf->getTargetDir();
}

public function getExtra(): array
{
return $this->aliasOf->getExtra();
}

public function setInstallationSource(?string $type): void
{
$this->aliasOf->setInstallationSource($type);
}

public function getInstallationSource(): ?string
{
return $this->aliasOf->getInstallationSource();
}

public function getSourceType(): ?string
{
return $this->aliasOf->getSourceType();
}

public function getSourceUrl(): ?string
{
return $this->aliasOf->getSourceUrl();
}

public function getSourceUrls(): array
{
return $this->aliasOf->getSourceUrls();
}

public function getSourceReference(): ?string
{
return $this->aliasOf->getSourceReference();
}

public function setSourceReference(?string $reference): void
{
$this->aliasOf->setSourceReference($reference);
}

public function setSourceMirrors(?array $mirrors): void
{
$this->aliasOf->setSourceMirrors($mirrors);
}

public function getSourceMirrors(): ?array
{
return $this->aliasOf->getSourceMirrors();
}

public function getDistType(): ?string
{
return $this->aliasOf->getDistType();
}

public function getDistUrl(): ?string
{
return $this->aliasOf->getDistUrl();
}

public function getDistUrls(): array
{
return $this->aliasOf->getDistUrls();
}

public function getDistReference(): ?string
{
return $this->aliasOf->getDistReference();
}

public function setDistReference(?string $reference): void
{
$this->aliasOf->setDistReference($reference);
}

public function getDistSha1Checksum(): ?string
{
return $this->aliasOf->getDistSha1Checksum();
}

public function setTransportOptions(array $options): void
{
$this->aliasOf->setTransportOptions($options);
}

public function getTransportOptions(): array
{
return $this->aliasOf->getTransportOptions();
}

public function setDistMirrors(?array $mirrors): void
{
$this->aliasOf->setDistMirrors($mirrors);
}

public function getDistMirrors(): ?array
{
return $this->aliasOf->getDistMirrors();
}

public function getAutoload(): array
{
return $this->aliasOf->getAutoload();
}

public function getDevAutoload(): array
{
return $this->aliasOf->getDevAutoload();
}

public function getIncludePaths(): array
{
return $this->aliasOf->getIncludePaths();
}

public function getReleaseDate(): ?\DateTimeInterface
{
return $this->aliasOf->getReleaseDate();
}

public function getBinaries(): array
{
return $this->aliasOf->getBinaries();
}

public function getSuggests(): array
{
return $this->aliasOf->getSuggests();
}

public function getNotificationUrl(): ?string
{
return $this->aliasOf->getNotificationUrl();
}

public function isDefaultBranch(): bool
{
return $this->aliasOf->isDefaultBranch();
}

public function setDistUrl(?string $url): void
{
$this->aliasOf->setDistUrl($url);
}

public function setDistType(?string $type): void
{
$this->aliasOf->setDistType($type);
}

public function setSourceDistReferences(string $reference): void
{
$this->aliasOf->setSourceDistReferences($reference);
}
}
