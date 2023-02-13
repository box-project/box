<?php declare(strict_types=1);











namespace Composer\Downloader;

use Composer\Package\PackageInterface;
use Composer\IO\IOInterface;
use Composer\Pcre\Preg;
use Composer\Util\Filesystem;
use Composer\Exception\IrrecoverableDownloadException;
use React\Promise\PromiseInterface;






class DownloadManager
{

private $io;

private $preferDist = false;

private $preferSource;

private $packagePreferences = [];

private $filesystem;

private $downloaders = [];








public function __construct(IOInterface $io, bool $preferSource = false, ?Filesystem $filesystem = null)
{
$this->io = $io;
$this->preferSource = $preferSource;
$this->filesystem = $filesystem ?: new Filesystem();
}







public function setPreferSource(bool $preferSource): self
{
$this->preferSource = $preferSource;

return $this;
}







public function setPreferDist(bool $preferDist): self
{
$this->preferDist = $preferDist;

return $this;
}








public function setPreferences(array $preferences): self
{
$this->packagePreferences = $preferences;

return $this;
}








public function setDownloader(string $type, DownloaderInterface $downloader): self
{
$type = strtolower($type);
$this->downloaders[$type] = $downloader;

return $this;
}







public function getDownloader(string $type): DownloaderInterface
{
$type = strtolower($type);
if (!isset($this->downloaders[$type])) {
throw new \InvalidArgumentException(sprintf('Unknown downloader type: %s. Available types: %s.', $type, implode(', ', array_keys($this->downloaders))));
}

return $this->downloaders[$type];
}









public function getDownloaderForPackage(PackageInterface $package): ?DownloaderInterface
{
$installationSource = $package->getInstallationSource();

if ('metapackage' === $package->getType()) {
return null;
}

if ('dist' === $installationSource) {
$downloader = $this->getDownloader($package->getDistType());
} elseif ('source' === $installationSource) {
$downloader = $this->getDownloader($package->getSourceType());
} else {
throw new \InvalidArgumentException(
'Package '.$package.' does not have an installation source set'
);
}

if ($installationSource !== $downloader->getInstallationSource()) {
throw new \LogicException(sprintf(
'Downloader "%s" is a %s type downloader and can not be used to download %s for package %s',
get_class($downloader),
$downloader->getInstallationSource(),
$installationSource,
$package
));
}

return $downloader;
}

public function getDownloaderType(DownloaderInterface $downloader): string
{
return array_search($downloader, $this->downloaders);
}











public function download(PackageInterface $package, string $targetDir, ?PackageInterface $prevPackage = null): PromiseInterface
{
$targetDir = $this->normalizeTargetDir($targetDir);
$this->filesystem->ensureDirectoryExists(dirname($targetDir));

$sources = $this->getAvailableSources($package, $prevPackage);

$io = $this->io;

$download = function ($retry = false) use (&$sources, $io, $package, $targetDir, &$download, $prevPackage) {
$source = array_shift($sources);
if ($retry) {
$io->writeError('    <warning>Now trying to download from ' . $source . '</warning>');
}
$package->setInstallationSource($source);

$downloader = $this->getDownloaderForPackage($package);
if (!$downloader) {
return \React\Promise\resolve(null);
}

$handleError = static function ($e) use ($sources, $source, $package, $io, $download) {
if ($e instanceof \RuntimeException && !$e instanceof IrrecoverableDownloadException) {
if (!$sources) {
throw $e;
}

$io->writeError(
'    <warning>Failed to download '.
$package->getPrettyName().
' from ' . $source . ': '.
$e->getMessage().'</warning>'
);

return $download(true);
}

throw $e;
};

try {
$result = $downloader->download($package, $targetDir, $prevPackage);
} catch (\Exception $e) {
return $handleError($e);
}

$res = $result->then(static function ($res) {
return $res;
}, $handleError);

return $res;
};

return $download();
}









public function prepare(string $type, PackageInterface $package, string $targetDir, ?PackageInterface $prevPackage = null): PromiseInterface
{
$targetDir = $this->normalizeTargetDir($targetDir);
$downloader = $this->getDownloaderForPackage($package);
if ($downloader) {
return $downloader->prepare($type, $package, $targetDir, $prevPackage);
}

return \React\Promise\resolve(null);
}










public function install(PackageInterface $package, string $targetDir): PromiseInterface
{
$targetDir = $this->normalizeTargetDir($targetDir);
$downloader = $this->getDownloaderForPackage($package);
if ($downloader) {
return $downloader->install($package, $targetDir);
}

return \React\Promise\resolve(null);
}










public function update(PackageInterface $initial, PackageInterface $target, string $targetDir): PromiseInterface
{
$targetDir = $this->normalizeTargetDir($targetDir);
$downloader = $this->getDownloaderForPackage($target);
$initialDownloader = $this->getDownloaderForPackage($initial);


if (!$initialDownloader && !$downloader) {
return \React\Promise\resolve(null);
}


if (!$downloader) {
return $initialDownloader->remove($initial, $targetDir);
}

$initialType = $this->getDownloaderType($initialDownloader);
$targetType = $this->getDownloaderType($downloader);
if ($initialType === $targetType) {
try {
return $downloader->update($initial, $target, $targetDir);
} catch (\RuntimeException $e) {
if (!$this->io->isInteractive()) {
throw $e;
}
$this->io->writeError('<error>    Update failed ('.$e->getMessage().')</error>');
if (!$this->io->askConfirmation('    Would you like to try reinstalling the package instead [<comment>yes</comment>]? ')) {
throw $e;
}
}
}



$promise = $initialDownloader->remove($initial, $targetDir);

return $promise->then(function ($res) use ($target, $targetDir): PromiseInterface {
return $this->install($target, $targetDir);
});
}







public function remove(PackageInterface $package, string $targetDir): PromiseInterface
{
$targetDir = $this->normalizeTargetDir($targetDir);
$downloader = $this->getDownloaderForPackage($package);
if ($downloader) {
return $downloader->remove($package, $targetDir);
}

return \React\Promise\resolve(null);
}









public function cleanup(string $type, PackageInterface $package, string $targetDir, ?PackageInterface $prevPackage = null): PromiseInterface
{
$targetDir = $this->normalizeTargetDir($targetDir);
$downloader = $this->getDownloaderForPackage($package);
if ($downloader) {
return $downloader->cleanup($type, $package, $targetDir, $prevPackage);
}

return \React\Promise\resolve(null);
}






protected function resolvePackageInstallPreference(PackageInterface $package): string
{
foreach ($this->packagePreferences as $pattern => $preference) {
$pattern = '{^'.str_replace('\\*', '.*', preg_quote($pattern)).'$}i';
if (Preg::isMatch($pattern, $package->getName())) {
if ('dist' === $preference || (!$package->isDev() && 'auto' === $preference)) {
return 'dist';
}

return 'source';
}
}

return $package->isDev() ? 'source' : 'dist';
}





private function getAvailableSources(PackageInterface $package, ?PackageInterface $prevPackage = null): array
{
$sourceType = $package->getSourceType();
$distType = $package->getDistType();


$sources = [];
if ($sourceType) {
$sources[] = 'source';
}
if ($distType) {
$sources[] = 'dist';
}

if (empty($sources)) {
throw new \InvalidArgumentException('Package '.$package.' must have a source or dist specified');
}

if (
$prevPackage

&& in_array($prevPackage->getInstallationSource(), $sources, true)

&& !(!$prevPackage->isDev() && $prevPackage->getInstallationSource() === 'dist' && $package->isDev())
) {
$prevSource = $prevPackage->getInstallationSource();
usort($sources, static function ($a, $b) use ($prevSource): int {
return $a === $prevSource ? -1 : 1;
});

return $sources;
}


if (!$this->preferSource && ($this->preferDist || 'dist' === $this->resolvePackageInstallPreference($package))) {
$sources = array_reverse($sources);
}

return $sources;
}






private function normalizeTargetDir(string $dir): string
{
if ($dir === '\\' || $dir === '/') {
return $dir;
}

return rtrim($dir, '\\/');
}
}
