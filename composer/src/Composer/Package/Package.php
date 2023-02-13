<?php declare(strict_types=1);











namespace Composer\Package;

use Composer\Package\Version\VersionParser;
use Composer\Pcre\Preg;
use Composer\Util\ComposerMirror;









class Package extends BasePackage
{

protected $type;

protected $targetDir;

protected $installationSource;

protected $sourceType;

protected $sourceUrl;

protected $sourceReference;

protected $sourceMirrors;

protected $distType;

protected $distUrl;

protected $distReference;

protected $distSha1Checksum;

protected $distMirrors;

protected $version;

protected $prettyVersion;

protected $releaseDate;

protected $extra = [];

protected $binaries = [];

protected $dev;




protected $stability;

protected $notificationUrl;


protected $requires = [];

protected $conflicts = [];

protected $provides = [];

protected $replaces = [];

protected $devRequires = [];

protected $suggests = [];




protected $autoload = [];




protected $devAutoload = [];

protected $includePaths = [];

protected $isDefaultBranch = false;

protected $transportOptions = [];








public function __construct(string $name, string $version, string $prettyVersion)
{
parent::__construct($name);

$this->version = $version;
$this->prettyVersion = $prettyVersion;

$this->stability = VersionParser::parseStability($version);
$this->dev = $this->stability === 'dev';
}




public function isDev(): bool
{
return $this->dev;
}

public function setType(string $type): void
{
$this->type = $type;
}




public function getType(): string
{
return $this->type ?: 'library';
}




public function getStability(): string
{
return $this->stability;
}

public function setTargetDir(?string $targetDir): void
{
$this->targetDir = $targetDir;
}




public function getTargetDir(): ?string
{
if (null === $this->targetDir) {
return null;
}

return ltrim(Preg::replace('{ (?:^|[\\\\/]+) \.\.? (?:[\\\\/]+|$) (?:\.\.? (?:[\\\\/]+|$) )*}x', '/', $this->targetDir), '/');
}




public function setExtra(array $extra): void
{
$this->extra = $extra;
}




public function getExtra(): array
{
return $this->extra;
}




public function setBinaries(array $binaries): void
{
$this->binaries = $binaries;
}




public function getBinaries(): array
{
return $this->binaries;
}




public function setInstallationSource(?string $type): void
{
$this->installationSource = $type;
}




public function getInstallationSource(): ?string
{
return $this->installationSource;
}

public function setSourceType(?string $type): void
{
$this->sourceType = $type;
}




public function getSourceType(): ?string
{
return $this->sourceType;
}

public function setSourceUrl(?string $url): void
{
$this->sourceUrl = $url;
}




public function getSourceUrl(): ?string
{
return $this->sourceUrl;
}

public function setSourceReference(?string $reference): void
{
$this->sourceReference = $reference;
}




public function getSourceReference(): ?string
{
return $this->sourceReference;
}

public function setSourceMirrors(?array $mirrors): void
{
$this->sourceMirrors = $mirrors;
}




public function getSourceMirrors(): ?array
{
return $this->sourceMirrors;
}




public function getSourceUrls(): array
{
return $this->getUrls($this->sourceUrl, $this->sourceMirrors, $this->sourceReference, $this->sourceType, 'source');
}




public function setDistType(?string $type): void
{
$this->distType = $type === '' ? null : $type;
}




public function getDistType(): ?string
{
return $this->distType;
}




public function setDistUrl(?string $url): void
{
$this->distUrl = $url === '' ? null : $url;
}




public function getDistUrl(): ?string
{
return $this->distUrl;
}




public function setDistReference(?string $reference): void
{
$this->distReference = $reference;
}




public function getDistReference(): ?string
{
return $this->distReference;
}




public function setDistSha1Checksum(?string $sha1checksum): void
{
$this->distSha1Checksum = $sha1checksum;
}




public function getDistSha1Checksum(): ?string
{
return $this->distSha1Checksum;
}

public function setDistMirrors(?array $mirrors): void
{
$this->distMirrors = $mirrors;
}




public function getDistMirrors(): ?array
{
return $this->distMirrors;
}




public function getDistUrls(): array
{
return $this->getUrls($this->distUrl, $this->distMirrors, $this->distReference, $this->distType, 'dist');
}




public function getTransportOptions(): array
{
return $this->transportOptions;
}




public function setTransportOptions(array $options): void
{
$this->transportOptions = $options;
}




public function getVersion(): string
{
return $this->version;
}




public function getPrettyVersion(): string
{
return $this->prettyVersion;
}

public function setReleaseDate(?\DateTimeInterface $releaseDate): void
{
$this->releaseDate = $releaseDate;
}




public function getReleaseDate(): ?\DateTimeInterface
{
return $this->releaseDate;
}






public function setRequires(array $requires): void
{
if (isset($requires[0])) { 
$requires = $this->convertLinksToMap($requires, 'setRequires');
}

$this->requires = $requires;
}




public function getRequires(): array
{
return $this->requires;
}






public function setConflicts(array $conflicts): void
{
if (isset($conflicts[0])) { 
$conflicts = $this->convertLinksToMap($conflicts, 'setConflicts');
}

$this->conflicts = $conflicts;
}





public function getConflicts(): array
{
return $this->conflicts;
}






public function setProvides(array $provides): void
{
if (isset($provides[0])) { 
$provides = $this->convertLinksToMap($provides, 'setProvides');
}

$this->provides = $provides;
}





public function getProvides(): array
{
return $this->provides;
}






public function setReplaces(array $replaces): void
{
if (isset($replaces[0])) { 
$replaces = $this->convertLinksToMap($replaces, 'setReplaces');
}

$this->replaces = $replaces;
}





public function getReplaces(): array
{
return $this->replaces;
}






public function setDevRequires(array $devRequires): void
{
if (isset($devRequires[0])) { 
$devRequires = $this->convertLinksToMap($devRequires, 'setDevRequires');
}

$this->devRequires = $devRequires;
}




public function getDevRequires(): array
{
return $this->devRequires;
}






public function setSuggests(array $suggests): void
{
$this->suggests = $suggests;
}




public function getSuggests(): array
{
return $this->suggests;
}








public function setAutoload(array $autoload): void
{
$this->autoload = $autoload;
}




public function getAutoload(): array
{
return $this->autoload;
}








public function setDevAutoload(array $devAutoload): void
{
$this->devAutoload = $devAutoload;
}




public function getDevAutoload(): array
{
return $this->devAutoload;
}






public function setIncludePaths(array $includePaths): void
{
$this->includePaths = $includePaths;
}




public function getIncludePaths(): array
{
return $this->includePaths;
}




public function setNotificationUrl(string $notificationUrl): void
{
$this->notificationUrl = $notificationUrl;
}




public function getNotificationUrl(): ?string
{
return $this->notificationUrl;
}

public function setIsDefaultBranch(bool $defaultBranch): void
{
$this->isDefaultBranch = $defaultBranch;
}




public function isDefaultBranch(): bool
{
return $this->isDefaultBranch;
}




public function setSourceDistReferences(string $reference): void
{
$this->setSourceReference($reference);



if (
$this->getDistUrl() !== null
&& Preg::isMatch('{^https?://(?:(?:www\.)?bitbucket\.org|(api\.)?github\.com|(?:www\.)?gitlab\.com)/}i', $this->getDistUrl())
) {
$this->setDistReference($reference);
$this->setDistUrl(Preg::replace('{(?<=/|sha=)[a-f0-9]{40}(?=/|$)}i', $reference, $this->getDistUrl()));
} elseif ($this->getDistReference()) { 
$this->setDistReference($reference);
}
}








public function replaceVersion(string $version, string $prettyVersion): void
{
$this->version = $version;
$this->prettyVersion = $prettyVersion;

$this->stability = VersionParser::parseStability($version);
$this->dev = $this->stability === 'dev';
}








protected function getUrls(?string $url, ?array $mirrors, ?string $ref, ?string $type, string $urlType): array
{
if (!$url) {
return [];
}

if ($urlType === 'dist' && false !== strpos($url, '%')) {
$url = ComposerMirror::processUrl($url, $this->name, $this->version, $ref, $type, $this->prettyVersion);
}

$urls = [$url];
if ($mirrors) {
foreach ($mirrors as $mirror) {
if ($urlType === 'dist') {
$mirrorUrl = ComposerMirror::processUrl($mirror['url'], $this->name, $this->version, $ref, $type, $this->prettyVersion);
} elseif ($urlType === 'source' && $type === 'git') {
$mirrorUrl = ComposerMirror::processGitUrl($mirror['url'], $this->name, $url, $type);
} elseif ($urlType === 'source' && $type === 'hg') {
$mirrorUrl = ComposerMirror::processHgUrl($mirror['url'], $this->name, $url, $type);
} else {
continue;
}
if (!\in_array($mirrorUrl, $urls)) {
$func = $mirror['preferred'] ? 'array_unshift' : 'array_push';
$func($urls, $mirrorUrl);
}
}
}

return $urls;
}





private function convertLinksToMap(array $links, string $source): array
{
trigger_error('Package::'.$source.' must be called with a map of lowercased package name => Link object, got a indexed array, this is deprecated and you should fix your usage.');
$newLinks = [];
foreach ($links as $link) {
$newLinks[$link->getTarget()] = $link;
}

return $newLinks;
}
}
