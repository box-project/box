<?php declare(strict_types=1);











namespace Composer\Downloader;

use Composer\Config;
use Composer\Cache;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Exception\IrrecoverableDownloadException;
use Composer\Package\Comparer\Comparer;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PostFileDownloadEvent;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Util\Filesystem;
use Composer\Util\Platform;
use Composer\Util\Silencer;
use Composer\Util\HttpDownloader;
use Composer\Util\Url as UrlUtil;
use Composer\Util\ProcessExecutor;
use React\Promise\PromiseInterface;









class FileDownloader implements DownloaderInterface, ChangeReportInterface
{

protected $io;

protected $config;

protected $httpDownloader;

protected $filesystem;

protected $cache;

protected $eventDispatcher;

protected $process;





public static $downloadMetadata = [];








public static $responseHeaders = [];




private $lastCacheWrites = [];

private $additionalCleanupPaths = [];











public function __construct(IOInterface $io, Config $config, HttpDownloader $httpDownloader, ?EventDispatcher $eventDispatcher = null, ?Cache $cache = null, ?Filesystem $filesystem = null, ?ProcessExecutor $process = null)
{
$this->io = $io;
$this->config = $config;
$this->eventDispatcher = $eventDispatcher;
$this->httpDownloader = $httpDownloader;
$this->cache = $cache;
$this->process = $process ?? new ProcessExecutor($io);
$this->filesystem = $filesystem ?: new Filesystem($this->process);

if ($this->cache && $this->cache->gcIsNecessary()) {
$this->io->writeError('Running cache garbage collection', true, IOInterface::VERY_VERBOSE);
$this->cache->gc($config->get('cache-files-ttl'), $config->get('cache-files-maxsize'));
}
}




public function getInstallationSource(): string
{
return 'dist';
}




public function download(PackageInterface $package, string $path, ?PackageInterface $prevPackage = null, bool $output = true): PromiseInterface
{
if (!$package->getDistUrl()) {
throw new \InvalidArgumentException('The given package is missing url information');
}

$cacheKeyGenerator = static function (PackageInterface $package, $key): string {
$cacheKey = sha1($key);

return $package->getName().'/'.$cacheKey.'.'.$package->getDistType();
};

$retries = 3;
$distUrls = $package->getDistUrls();

$urls = [];
foreach ($distUrls as $index => $url) {
$processedUrl = $this->processUrl($package, $url);
$urls[$index] = [
'base' => $url,
'processed' => $processedUrl,




'cacheKey' => $cacheKeyGenerator($package, $processedUrl),
];
}

$fileName = $this->getFileName($package, $path);
$this->filesystem->ensureDirectoryExists($path);
$this->filesystem->ensureDirectoryExists(dirname($fileName));

$io = $this->io;
$cache = $this->cache;
$httpDownloader = $this->httpDownloader;
$eventDispatcher = $this->eventDispatcher;
$filesystem = $this->filesystem;

$accept = null;
$reject = null;
$download = function () use ($io, $output, $httpDownloader, $cache, $cacheKeyGenerator, $eventDispatcher, $package, $fileName, &$urls, &$accept, &$reject) {
$url = reset($urls);
$index = key($urls);

if ($eventDispatcher) {
$preFileDownloadEvent = new PreFileDownloadEvent(PluginEvents::PRE_FILE_DOWNLOAD, $httpDownloader, $url['processed'], 'package', $package);
$eventDispatcher->dispatch($preFileDownloadEvent->getName(), $preFileDownloadEvent);
if ($preFileDownloadEvent->getCustomCacheKey() !== null) {
$url['cacheKey'] = $cacheKeyGenerator($package, $preFileDownloadEvent->getCustomCacheKey());
} elseif ($preFileDownloadEvent->getProcessedUrl() !== $url['processed']) {
$url['cacheKey'] = $cacheKeyGenerator($package, $preFileDownloadEvent->getProcessedUrl());
}
$url['processed'] = $preFileDownloadEvent->getProcessedUrl();
}

$urls[$index] = $url;

$checksum = $package->getDistSha1Checksum();
$cacheKey = $url['cacheKey'];


if ($cache && (!$checksum || $checksum === $cache->sha1($cacheKey)) && $cache->copyTo($cacheKey, $fileName)) {
if ($output) {
$io->writeError("  - Loading <info>" . $package->getName() . "</info> (<comment>" . $package->getFullPrettyVersion() . "</comment>) from cache", true, IOInterface::VERY_VERBOSE);
}



if (!$cache->isReadOnly()) {
$this->lastCacheWrites[$package->getName()] = $cacheKey;
}
$result = \React\Promise\resolve($fileName);
} else {
if ($output) {
$io->writeError("  - Downloading <info>" . $package->getName() . "</info> (<comment>" . $package->getFullPrettyVersion() . "</comment>)");
}

$result = $httpDownloader->addCopy($url['processed'], $fileName, $package->getTransportOptions())
->then($accept, $reject);
}

return $result->then(static function ($result) use ($fileName, $checksum, $url, $package, $eventDispatcher): string {



if (null === $result) {
return $fileName;
}

if (!file_exists($fileName)) {
throw new \UnexpectedValueException($url['base'].' could not be saved to '.$fileName.', make sure the'
.' directory is writable and you have internet connectivity');
}

if ($checksum && hash_file('sha1', $fileName) !== $checksum) {
throw new \UnexpectedValueException('The checksum verification of the file failed (downloaded from '.$url['base'].')');
}

if ($eventDispatcher) {
$postFileDownloadEvent = new PostFileDownloadEvent(PluginEvents::POST_FILE_DOWNLOAD, $fileName, $checksum, $url['processed'], 'package', $package);
$eventDispatcher->dispatch($postFileDownloadEvent->getName(), $postFileDownloadEvent);
}

return $fileName;
});
};

$accept = function ($response) use ($cache, $package, $fileName, &$urls): string {
$url = reset($urls);
$cacheKey = $url['cacheKey'];
FileDownloader::$downloadMetadata[$package->getName()] = @filesize($fileName) ?: $response->getHeader('Content-Length') ?: '?';

if (Platform::getEnv('GITHUB_ACTIONS') !== false && Platform::getEnv('COMPOSER_TESTS_ARE_RUNNING') === false) {
FileDownloader::$responseHeaders[$package->getName()] = $response->getHeaders();
}

if ($cache && !$cache->isReadOnly()) {
$this->lastCacheWrites[$package->getName()] = $cacheKey;
$cache->copyFrom($cacheKey, $fileName);
}

$response->collect();

return $fileName;
};

$reject = function ($e) use ($io, &$urls, $download, $fileName, $package, &$retries, $filesystem) {

if (file_exists($fileName)) {
$filesystem->unlink($fileName);
}
$this->clearLastCacheWrite($package);

if ($e instanceof IrrecoverableDownloadException) {
throw $e;
}

if ($e instanceof MaxFileSizeExceededException) {
throw $e;
}

if ($e instanceof TransportException) {

if ((0 !== $e->getCode() && !in_array($e->getCode(), [500, 502, 503, 504])) || !$retries) {
$retries = 0;
}
}


if ($e instanceof TransportException && $e->getStatusCode() === 499) {
$retries = 0;
$urls = [];
}

if ($retries) {
usleep(500000);
$retries--;

return $download();
}

array_shift($urls);
if ($urls) {
if ($io->isDebug()) {
$io->writeError('    Failed downloading '.$package->getName().': ['.get_class($e).'] '.$e->getCode().': '.$e->getMessage());
$io->writeError('    Trying the next URL for '.$package->getName());
} else {
$io->writeError('    Failed downloading '.$package->getName().', trying the next URL ('.$e->getCode().': '.$e->getMessage().')');
}

$retries = 3;
usleep(100000);

return $download();
}

throw $e;
};

return $download();
}




public function prepare(string $type, PackageInterface $package, string $path, ?PackageInterface $prevPackage = null): PromiseInterface
{
return \React\Promise\resolve(null);
}




public function cleanup(string $type, PackageInterface $package, string $path, ?PackageInterface $prevPackage = null): PromiseInterface
{
$fileName = $this->getFileName($package, $path);
if (file_exists($fileName)) {
$this->filesystem->unlink($fileName);
}

$dirsToCleanUp = [
$path,
$this->config->get('vendor-dir').'/'.explode('/', $package->getPrettyName())[0],
$this->config->get('vendor-dir').'/composer/',
$this->config->get('vendor-dir'),
];

if (isset($this->additionalCleanupPaths[$package->getName()])) {
foreach ($this->additionalCleanupPaths[$package->getName()] as $path) {
$this->filesystem->remove($path);
}
}

foreach ($dirsToCleanUp as $dir) {
if (is_dir($dir) && $this->filesystem->isDirEmpty($dir) && realpath($dir) !== Platform::getCwd()) {
$this->filesystem->removeDirectoryPhp($dir);
}
}

return \React\Promise\resolve(null);
}




public function install(PackageInterface $package, string $path, bool $output = true): PromiseInterface
{
if ($output) {
$this->io->writeError("  - " . InstallOperation::format($package));
}

$this->filesystem->emptyDirectory($path);
$this->filesystem->ensureDirectoryExists($path);
$this->filesystem->rename($this->getFileName($package, $path), $path . '/' . pathinfo(parse_url(strtr((string) $package->getDistUrl(), '\\', '/'), PHP_URL_PATH), PATHINFO_BASENAME));

if ($package->getBinaries()) {


foreach ($package->getBinaries() as $bin) {
if (file_exists($path . '/' . $bin) && !is_executable($path . '/' . $bin)) {
Silencer::call('chmod', $path . '/' . $bin, 0777 & ~umask());
}
}
}

return \React\Promise\resolve(null);
}

protected function clearLastCacheWrite(PackageInterface $package): void
{
if ($this->cache && isset($this->lastCacheWrites[$package->getName()])) {
$this->cache->remove($this->lastCacheWrites[$package->getName()]);
unset($this->lastCacheWrites[$package->getName()]);
}
}

protected function addCleanupPath(PackageInterface $package, string $path): void
{
$this->additionalCleanupPaths[$package->getName()][] = $path;
}

protected function removeCleanupPath(PackageInterface $package, string $path): void
{
if (isset($this->additionalCleanupPaths[$package->getName()])) {
$idx = array_search($path, $this->additionalCleanupPaths[$package->getName()]);
if (false !== $idx) {
unset($this->additionalCleanupPaths[$package->getName()][$idx]);
}
}
}




public function update(PackageInterface $initial, PackageInterface $target, string $path): PromiseInterface
{
$this->io->writeError("  - " . UpdateOperation::format($initial, $target) . $this->getInstallOperationAppendix($target, $path));

$promise = $this->remove($initial, $path, false);

return $promise->then(function () use ($target, $path): PromiseInterface {
return $this->install($target, $path, false);
});
}




public function remove(PackageInterface $package, string $path, bool $output = true): PromiseInterface
{
if ($output) {
$this->io->writeError("  - " . UninstallOperation::format($package));
}
$promise = $this->filesystem->removeDirectoryAsync($path);

return $promise->then(static function ($result) use ($path): void {
if (!$result) {
throw new \RuntimeException('Could not completely delete '.$path.', aborting.');
}
});
}








protected function getFileName(PackageInterface $package, string $path): string
{
return rtrim($this->config->get('vendor-dir').'/composer/tmp-'.md5($package.spl_object_hash($package)).'.'.pathinfo(parse_url(strtr((string) $package->getDistUrl(), '\\', '/'), PHP_URL_PATH), PATHINFO_EXTENSION), '.');
}







protected function getInstallOperationAppendix(PackageInterface $package, string $path): string
{
return '';
}









protected function processUrl(PackageInterface $package, string $url): string
{
if (!extension_loaded('openssl') && 0 === strpos($url, 'https:')) {
throw new \RuntimeException('You must enable the openssl extension to download files via https');
}

if ($package->getDistReference()) {
$url = UrlUtil::updateDistReference($this->config, $url, $package->getDistReference());
}

return $url;
}





public function getLocalChanges(PackageInterface $package, string $path): ?string
{
$prevIO = $this->io;

$this->io = new NullIO;
$this->io->loadConfiguration($this->config);
$e = null;
$output = '';

$targetDir = Filesystem::trimTrailingSlash($path);
try {
if (is_dir($targetDir.'_compare')) {
$this->filesystem->removeDirectory($targetDir.'_compare');
}

$this->download($package, $targetDir.'_compare', null, false);
$this->httpDownloader->wait();
$this->install($package, $targetDir.'_compare', false);
$this->process->wait();

$comparer = new Comparer();
$comparer->setSource($targetDir.'_compare');
$comparer->setUpdate($targetDir);
$comparer->doCompare();
$output = $comparer->getChangedAsString(true);
$this->filesystem->removeDirectory($targetDir.'_compare');
} catch (\Exception $e) {
}

$this->io = $prevIO;

if ($e) {
throw $e;
}

$output = trim($output);

return strlen($output) > 0 ? $output : null;
}
}
