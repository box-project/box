<?php declare(strict_types=1);











namespace Composer\Downloader;

use Composer\Config;
use Composer\Package\Dumper\ArrayDumper;
use Composer\Package\PackageInterface;
use Composer\Package\Version\VersionGuesser;
use Composer\Package\Version\VersionParser;
use Composer\Util\ProcessExecutor;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use React\Promise\PromiseInterface;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;




abstract class VcsDownloader implements DownloaderInterface, ChangeReportInterface, VcsCapableDownloaderInterface
{

protected $io;

protected $config;

protected $process;

protected $filesystem;

protected $hasCleanedChanges = [];

public function __construct(IOInterface $io, Config $config, ?ProcessExecutor $process = null, ?Filesystem $fs = null)
{
$this->io = $io;
$this->config = $config;
$this->process = $process ?? new ProcessExecutor($io);
$this->filesystem = $fs ?? new Filesystem($this->process);
}




public function getInstallationSource(): string
{
return 'source';
}




public function download(PackageInterface $package, string $path, ?PackageInterface $prevPackage = null): PromiseInterface
{
if (!$package->getSourceReference()) {
throw new \InvalidArgumentException('Package '.$package->getPrettyName().' is missing reference information');
}

$urls = $this->prepareUrls($package->getSourceUrls());

while ($url = array_shift($urls)) {
try {
return $this->doDownload($package, $path, $url, $prevPackage);
} catch (\Exception $e) {

if ($e instanceof \PHPUnit\Framework\Exception) {
throw $e;
}
if ($this->io->isDebug()) {
$this->io->writeError('Failed: ['.get_class($e).'] '.$e->getMessage());
} elseif (count($urls)) {
$this->io->writeError('    Failed, trying the next URL');
}
if (!count($urls)) {
throw $e;
}
}
}

return \React\Promise\resolve(null);
}




public function prepare(string $type, PackageInterface $package, string $path, ?PackageInterface $prevPackage = null): PromiseInterface
{
if ($type === 'update') {
$this->cleanChanges($prevPackage, $path, true);
$this->hasCleanedChanges[$prevPackage->getUniqueName()] = true;
} elseif ($type === 'install') {
$this->filesystem->emptyDirectory($path);
} elseif ($type === 'uninstall') {
$this->cleanChanges($package, $path, false);
}

return \React\Promise\resolve(null);
}




public function cleanup(string $type, PackageInterface $package, string $path, ?PackageInterface $prevPackage = null): PromiseInterface
{
if ($type === 'update' && isset($this->hasCleanedChanges[$prevPackage->getUniqueName()])) {
$this->reapplyChanges($path);
unset($this->hasCleanedChanges[$prevPackage->getUniqueName()]);
}

return \React\Promise\resolve(null);
}




public function install(PackageInterface $package, string $path): PromiseInterface
{
if (!$package->getSourceReference()) {
throw new \InvalidArgumentException('Package '.$package->getPrettyName().' is missing reference information');
}

$this->io->writeError("  - " . InstallOperation::format($package).': ', false);

$urls = $this->prepareUrls($package->getSourceUrls());
while ($url = array_shift($urls)) {
try {
$this->doInstall($package, $path, $url);
break;
} catch (\Exception $e) {

if ($e instanceof \PHPUnit\Framework\Exception) {
throw $e;
}
if ($this->io->isDebug()) {
$this->io->writeError('Failed: ['.get_class($e).'] '.$e->getMessage());
} elseif (count($urls)) {
$this->io->writeError('    Failed, trying the next URL');
}
if (!count($urls)) {
throw $e;
}
}
}

return \React\Promise\resolve(null);
}




public function update(PackageInterface $initial, PackageInterface $target, string $path): PromiseInterface
{
if (!$target->getSourceReference()) {
throw new \InvalidArgumentException('Package '.$target->getPrettyName().' is missing reference information');
}

$this->io->writeError("  - " . UpdateOperation::format($initial, $target).': ', false);

$urls = $this->prepareUrls($target->getSourceUrls());

$exception = null;
while ($url = array_shift($urls)) {
try {
$this->doUpdate($initial, $target, $path, $url);

$exception = null;
break;
} catch (\Exception $exception) {

if ($exception instanceof \PHPUnit\Framework\Exception) {
throw $exception;
}
if ($this->io->isDebug()) {
$this->io->writeError('Failed: ['.get_class($exception).'] '.$exception->getMessage());
} elseif (count($urls)) {
$this->io->writeError('    Failed, trying the next URL');
}
}
}



if (!$exception && $this->io->isVerbose() && $this->hasMetadataRepository($path)) {
$message = 'Pulling in changes:';
$logs = $this->getCommitLogs($initial->getSourceReference(), $target->getSourceReference(), $path);

if ('' === trim($logs)) {
$message = 'Rolling back changes:';
$logs = $this->getCommitLogs($target->getSourceReference(), $initial->getSourceReference(), $path);
}

if ('' !== trim($logs)) {
$logs = implode("\n", array_map(static function ($line): string {
return '      ' . $line;
}, explode("\n", $logs)));


$logs = str_replace('<', '\<', $logs);

$this->io->writeError('    '.$message);
$this->io->writeError($logs);
}
}

if (!$urls && $exception) {
throw $exception;
}

return \React\Promise\resolve(null);
}




public function remove(PackageInterface $package, string $path): PromiseInterface
{
$this->io->writeError("  - " . UninstallOperation::format($package));

$promise = $this->filesystem->removeDirectoryAsync($path);

return $promise->then(static function (bool $result) use ($path) {
if (!$result) {
throw new \RuntimeException('Could not completely delete '.$path.', aborting.');
}
});
}




public function getVcsReference(PackageInterface $package, string $path): ?string
{
$parser = new VersionParser;
$guesser = new VersionGuesser($this->config, $this->process, $parser);
$dumper = new ArrayDumper;

$packageConfig = $dumper->dump($package);
if ($packageVersion = $guesser->guessVersion($packageConfig, $path)) {
return $packageVersion['commit'];
}

return null;
}









protected function cleanChanges(PackageInterface $package, string $path, bool $update): PromiseInterface
{

if (null !== $this->getLocalChanges($package, $path)) {
throw new \RuntimeException('Source directory ' . $path . ' has uncommitted changes.');
}

return \React\Promise\resolve(null);
}






protected function reapplyChanges(string $path): void
{
}









abstract protected function doDownload(PackageInterface $package, string $path, string $url, ?PackageInterface $prevPackage = null): PromiseInterface;








abstract protected function doInstall(PackageInterface $package, string $path, string $url): PromiseInterface;









abstract protected function doUpdate(PackageInterface $initial, PackageInterface $target, string $path, string $url): PromiseInterface;








abstract protected function getCommitLogs(string $fromReference, string $toReference, string $path): string;





abstract protected function hasMetadataRepository(string $path): bool;






private function prepareUrls(array $urls): array
{
foreach ($urls as $index => $url) {
if (Filesystem::isLocalPath($url)) {


$fileProtocol = 'file://';
$isFileProtocol = false;
if (0 === strpos($url, $fileProtocol)) {
$url = substr($url, strlen($fileProtocol));
$isFileProtocol = true;
}


if (false !== strpos($url, '%')) {
$url = rawurldecode($url);
}

$urls[$index] = realpath($url);

if ($isFileProtocol) {
$urls[$index] = $fileProtocol . $urls[$index];
}
}
}

return $urls;
}
}
