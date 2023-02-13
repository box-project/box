<?php declare(strict_types=1);











namespace Composer\Downloader;

use React\Promise\PromiseInterface;
use Composer\Package\Archiver\ArchivableFilesFinder;
use Composer\Package\Dumper\ArrayDumper;
use Composer\Package\PackageInterface;
use Composer\Package\Version\VersionGuesser;
use Composer\Package\Version\VersionParser;
use Composer\Util\Platform;
use Composer\Util\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;







class PathDownloader extends FileDownloader implements VcsCapableDownloaderInterface
{
private const STRATEGY_SYMLINK = 10;
private const STRATEGY_MIRROR = 20;




public function download(PackageInterface $package, string $path, ?PackageInterface $prevPackage = null, bool $output = true): PromiseInterface
{
$path = Filesystem::trimTrailingSlash($path);
$url = $package->getDistUrl();
$realUrl = realpath($url);
if (false === $realUrl || !file_exists($realUrl) || !is_dir($realUrl)) {
throw new \RuntimeException(sprintf(
'Source path "%s" is not found for package %s',
$url,
$package->getName()
));
}

if (realpath($path) === $realUrl) {
return \React\Promise\resolve(null);
}

if (strpos(realpath($path) . DIRECTORY_SEPARATOR, $realUrl . DIRECTORY_SEPARATOR) === 0) {




throw new \RuntimeException(sprintf(
'Package %s cannot install to "%s" inside its source at "%s"',
$package->getName(),
realpath($path),
$realUrl
));
}

return \React\Promise\resolve(null);
}




public function install(PackageInterface $package, string $path, bool $output = true): PromiseInterface
{
$path = Filesystem::trimTrailingSlash($path);
$url = $package->getDistUrl();
$realUrl = realpath($url);

if (realpath($path) === $realUrl) {
if ($output) {
$this->io->writeError("  - " . InstallOperation::format($package) . $this->getInstallOperationAppendix($package, $path));
}

return \React\Promise\resolve(null);
}


$transportOptions = $package->getTransportOptions() + ['relative' => true];

[$currentStrategy, $allowedStrategies] = $this->computeAllowedStrategies($transportOptions);

$symfonyFilesystem = new SymfonyFilesystem();
$this->filesystem->removeDirectory($path);

if ($output) {
$this->io->writeError("  - " . InstallOperation::format($package).': ', false);
}

$isFallback = false;
if (self::STRATEGY_SYMLINK === $currentStrategy) {
try {
if (Platform::isWindows()) {

if ($output) {
$this->io->writeError(sprintf('Junctioning from %s', $url), false);
}
$this->filesystem->junction($realUrl, $path);
} else {
$absolutePath = $path;
if (!$this->filesystem->isAbsolutePath($absolutePath)) {
$absolutePath = Platform::getCwd() . DIRECTORY_SEPARATOR . $path;
}
$shortestPath = $this->filesystem->findShortestPath($absolutePath, $realUrl);
$path = rtrim($path, "/");
if ($output) {
$this->io->writeError(sprintf('Symlinking from %s', $url), false);
}
if ($transportOptions['relative']) {
$symfonyFilesystem->symlink($shortestPath.'/', $path);
} else {
$symfonyFilesystem->symlink($realUrl.'/', $path);
}
}
} catch (IOException $e) {
if (in_array(self::STRATEGY_MIRROR, $allowedStrategies, true)) {
if ($output) {
$this->io->writeError('');
$this->io->writeError('    <error>Symlink failed, fallback to use mirroring!</error>');
}
$currentStrategy = self::STRATEGY_MIRROR;
$isFallback = true;
} else {
throw new \RuntimeException(sprintf('Symlink from "%s" to "%s" failed!', $realUrl, $path));
}
}
}


if (self::STRATEGY_MIRROR === $currentStrategy) {
$realUrl = $this->filesystem->normalizePath($realUrl);

if ($output) {
$this->io->writeError(sprintf('%sMirroring from %s', $isFallback ? '    ' : '', $url), false);
}
$iterator = new ArchivableFilesFinder($realUrl, []);
$symfonyFilesystem->mirror($realUrl, $path, $iterator);
}

if ($output) {
$this->io->writeError('');
}

return \React\Promise\resolve(null);
}




public function remove(PackageInterface $package, string $path, bool $output = true): PromiseInterface
{
$path = Filesystem::trimTrailingSlash($path);









if (Platform::isWindows() && $this->filesystem->isJunction($path)) {
if ($output) {
$this->io->writeError("  - " . UninstallOperation::format($package).", source is still present in $path");
}
if (!$this->filesystem->removeJunction($path)) {
$this->io->writeError("    <warning>Could not remove junction at " . $path . " - is another process locking it?</warning>");
throw new \RuntimeException('Could not reliably remove junction for package ' . $package->getName());
}

return \React\Promise\resolve(null);
}





$fs = new Filesystem;
$absPath = $fs->isAbsolutePath($path) ? $path : Platform::getCwd() . '/' . $path;
$absDistUrl = $fs->isAbsolutePath($package->getDistUrl()) ? $package->getDistUrl() : Platform::getCwd() . '/' . $package->getDistUrl();
if ($fs->normalizePath($absPath) === $fs->normalizePath($absDistUrl)) {
if ($output) {
$this->io->writeError("  - " . UninstallOperation::format($package).", source is still present in $path");
}

return \React\Promise\resolve(null);
}

return parent::remove($package, $path, $output);
}




public function getVcsReference(PackageInterface $package, string $path): ?string
{
$path = Filesystem::trimTrailingSlash($path);
$parser = new VersionParser;
$guesser = new VersionGuesser($this->config, $this->process, $parser);
$dumper = new ArrayDumper;

$packageConfig = $dumper->dump($package);
if ($packageVersion = $guesser->guessVersion($packageConfig, $path)) {
return $packageVersion['commit'];
}

return null;
}




protected function getInstallOperationAppendix(PackageInterface $package, string $path): string
{
$realUrl = realpath($package->getDistUrl());

if (realpath($path) === $realUrl) {
return ': Source already present';
}

[$currentStrategy] = $this->computeAllowedStrategies($package->getTransportOptions());

if ($currentStrategy === self::STRATEGY_SYMLINK) {
if (Platform::isWindows()) {
return ': Junctioning from '.$package->getDistUrl();
}

return ': Symlinking from '.$package->getDistUrl();
}

return ': Mirroring from '.$package->getDistUrl();
}






private function computeAllowedStrategies(array $transportOptions): array
{

$currentStrategy = self::STRATEGY_SYMLINK;
$allowedStrategies = [self::STRATEGY_SYMLINK, self::STRATEGY_MIRROR];

$mirrorPathRepos = Platform::getEnv('COMPOSER_MIRROR_PATH_REPOS');
if ($mirrorPathRepos) {
$currentStrategy = self::STRATEGY_MIRROR;
}

$symlinkOption = $transportOptions['symlink'] ?? null;

if (true === $symlinkOption) {
$currentStrategy = self::STRATEGY_SYMLINK;
$allowedStrategies = [self::STRATEGY_SYMLINK];
} elseif (false === $symlinkOption) {
$currentStrategy = self::STRATEGY_MIRROR;
$allowedStrategies = [self::STRATEGY_MIRROR];
}


if (Platform::isWindows() && self::STRATEGY_SYMLINK === $currentStrategy && !$this->safeJunctions()) {
if (!in_array(self::STRATEGY_MIRROR, $allowedStrategies, true)) {
throw new \RuntimeException('You are on an old Windows / old PHP combo which does not allow Composer to use junctions/symlinks and this path repository has symlink:true in its options so copying is not allowed');
}
$currentStrategy = self::STRATEGY_MIRROR;
$allowedStrategies = [self::STRATEGY_MIRROR];
}


if (!Platform::isWindows() && self::STRATEGY_SYMLINK === $currentStrategy && !function_exists('symlink')) {
if (!in_array(self::STRATEGY_MIRROR, $allowedStrategies, true)) {
throw new \RuntimeException('Your PHP has the symlink() function disabled which does not allow Composer to use symlinks and this path repository has symlink:true in its options so copying is not allowed');
}
$currentStrategy = self::STRATEGY_MIRROR;
$allowedStrategies = [self::STRATEGY_MIRROR];
}

return [$currentStrategy, $allowedStrategies];
}












private function safeJunctions(): bool
{

return function_exists('proc_open') &&
(PHP_WINDOWS_VERSION_MAJOR > 6 ||
(PHP_WINDOWS_VERSION_MAJOR === 6 && PHP_WINDOWS_VERSION_MINOR >= 1));
}
}
