<?php declare(strict_types=1);











namespace Composer\Package\Archiver;

use Composer\Downloader\DownloadManager;
use Composer\Package\RootPackageInterface;
use Composer\Pcre\Preg;
use Composer\Util\Filesystem;
use Composer\Util\Loop;
use Composer\Util\SyncHelper;
use Composer\Json\JsonFile;
use Composer\Package\CompletePackageInterface;





class ArchiveManager
{

protected $downloadManager;

protected $loop;




protected $archivers = [];




protected $overwriteFiles = true;




public function __construct(DownloadManager $downloadManager, Loop $loop)
{
$this->downloadManager = $downloadManager;
$this->loop = $loop;
}

public function addArchiver(ArchiverInterface $archiver): void
{
$this->archivers[] = $archiver;
}








public function setOverwriteFiles(bool $overwriteFiles): self
{
$this->overwriteFiles = $overwriteFiles;

return $this;
}





public function getPackageFilenameParts(CompletePackageInterface $package): array
{
$baseName = $package->getArchiveName();
if (null === $baseName) {
$baseName = Preg::replace('#[^a-z0-9-_]#i', '-', $package->getName());
}

$parts = [
'base' => $baseName,
];

$distReference = $package->getDistReference();
if (null !== $distReference && Preg::isMatch('{^[a-f0-9]{40}$}', $distReference)) {
$parts['dist_reference'] = $distReference;
$parts['dist_type'] = $package->getDistType();
} else {
$parts['version'] = $package->getPrettyVersion();
$parts['dist_reference'] = $distReference;
}

$sourceReference = $package->getSourceReference();
if (null !== $sourceReference) {
$parts['source_reference'] = substr(sha1($sourceReference), 0, 6);
}

$parts = array_filter($parts);
foreach ($parts as $key => $part) {
$parts[$key] = str_replace('/', '-', $part);
}

return $parts;
}







public function getPackageFilenameFromParts(array $parts): string
{
return implode('-', $parts);
}








public function getPackageFilename(CompletePackageInterface $package): string
{
return $this->getPackageFilenameFromParts($this->getPackageFilenameParts($package));
}














public function archive(CompletePackageInterface $package, string $format, string $targetDir, ?string $fileName = null, bool $ignoreFilters = false): string
{
if (empty($format)) {
throw new \InvalidArgumentException('Format must be specified');
}


$usableArchiver = null;
foreach ($this->archivers as $archiver) {
if ($archiver->supports($format, $package->getSourceType())) {
$usableArchiver = $archiver;
break;
}
}


if (null === $usableArchiver) {
throw new \RuntimeException(sprintf('No archiver found to support %s format', $format));
}

$filesystem = new Filesystem();

if ($package instanceof RootPackageInterface) {
$sourcePath = realpath('.');
} else {

$sourcePath = sys_get_temp_dir().'/composer_archive'.uniqid();
$filesystem->ensureDirectoryExists($sourcePath);

try {

$promise = $this->downloadManager->download($package, $sourcePath);
SyncHelper::await($this->loop, $promise);
$promise = $this->downloadManager->install($package, $sourcePath);
SyncHelper::await($this->loop, $promise);
} catch (\Exception $e) {
$filesystem->removeDirectory($sourcePath);
throw $e;
}


if (file_exists($composerJsonPath = $sourcePath.'/composer.json')) {
$jsonFile = new JsonFile($composerJsonPath);
$jsonData = $jsonFile->read();
if (!empty($jsonData['archive']['name'])) {
$package->setArchiveName($jsonData['archive']['name']);
}
if (!empty($jsonData['archive']['exclude'])) {
$package->setArchiveExcludes($jsonData['archive']['exclude']);
}
}
}

$supportedFormats = $this->getSupportedFormats();
$packageNameParts = null === $fileName ?
$this->getPackageFilenameParts($package)
: ['base' => $fileName];

$packageName = $this->getPackageFilenameFromParts($packageNameParts);
$excludePatterns = $this->buildExcludePatterns($packageNameParts, $supportedFormats);


$filesystem->ensureDirectoryExists($targetDir);
$target = realpath($targetDir).'/'.$packageName.'.'.$format;
$filesystem->ensureDirectoryExists(dirname($target));

if (!$this->overwriteFiles && file_exists($target)) {
return $target;
}


$tempTarget = sys_get_temp_dir().'/composer_archive'.uniqid().'.'.$format;
$filesystem->ensureDirectoryExists(dirname($tempTarget));

$archivePath = $usableArchiver->archive(
$sourcePath,
$tempTarget,
$format,
array_merge($excludePatterns, $package->getArchiveExcludes()),
$ignoreFilters
);
$filesystem->rename($archivePath, $target);


if (!$package instanceof RootPackageInterface) {
$filesystem->removeDirectory($sourcePath);
}
$filesystem->remove($tempTarget);

return $target;
}







private function buildExcludePatterns(array $parts, array $formats): array
{
$base = $parts['base'];
if (count($parts) > 1) {
$base .= '-*';
}

$patterns = [];
foreach ($formats as $format) {
$patterns[] = "$base.$format";
}

return $patterns;
}




private function getSupportedFormats(): array
{





$formats = [];
foreach ($this->archivers as $archiver) {
$items = [];
switch (get_class($archiver)) {
case ZipArchiver::class:
$items = ['zip'];
break;

case PharArchiver::class:
$items = ['zip', 'tar', 'tar.gz', 'tar.bz2'];
break;
}

$formats = array_merge($formats, $items);
}

return array_unique($formats);
}
}
