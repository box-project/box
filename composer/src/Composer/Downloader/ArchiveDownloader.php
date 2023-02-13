<?php declare(strict_types=1);











namespace Composer\Downloader;

use Composer\Package\PackageInterface;
use Composer\Util\Platform;
use Symfony\Component\Finder\Finder;
use React\Promise\PromiseInterface;
use Composer\DependencyResolver\Operation\InstallOperation;








abstract class ArchiveDownloader extends FileDownloader
{



protected $cleanupExecuted = [];

public function prepare(string $type, PackageInterface $package, string $path, ?PackageInterface $prevPackage = null): PromiseInterface
{
unset($this->cleanupExecuted[$package->getName()]);

return parent::prepare($type, $package, $path, $prevPackage);
}

public function cleanup(string $type, PackageInterface $package, string $path, ?PackageInterface $prevPackage = null): PromiseInterface
{
$this->cleanupExecuted[$package->getName()] = true;

return parent::cleanup($type, $package, $path, $prevPackage);
}







public function install(PackageInterface $package, string $path, bool $output = true): PromiseInterface
{
if ($output) {
$this->io->writeError("  - " . InstallOperation::format($package) . $this->getInstallOperationAppendix($package, $path));
}

$vendorDir = $this->config->get('vendor-dir');




if (false === strpos($this->filesystem->normalizePath($vendorDir), $this->filesystem->normalizePath($path.DIRECTORY_SEPARATOR))) {
$this->filesystem->emptyDirectory($path);
}

do {
$temporaryDir = $vendorDir.'/composer/'.substr(md5(uniqid('', true)), 0, 8);
} while (is_dir($temporaryDir));

$this->addCleanupPath($package, $temporaryDir);


if (!is_dir($path) || realpath($path) !== Platform::getCwd()) {
$this->addCleanupPath($package, $path);
}

$this->filesystem->ensureDirectoryExists($temporaryDir);
$fileName = $this->getFileName($package, $path);

$filesystem = $this->filesystem;

$cleanup = function () use ($path, $filesystem, $temporaryDir, $package) {

$this->clearLastCacheWrite($package);


$filesystem->removeDirectory($temporaryDir);
if (is_dir($path) && realpath($path) !== Platform::getCwd()) {
$filesystem->removeDirectory($path);
}
$this->removeCleanupPath($package, $temporaryDir);
$realpath = realpath($path);
if ($realpath !== false) {
$this->removeCleanupPath($package, $realpath);
}
};

try {
$promise = $this->extract($package, $fileName, $temporaryDir);
} catch (\Exception $e) {
$cleanup();
throw $e;
}

return $promise->then(function () use ($package, $filesystem, $fileName, $temporaryDir, $path): \React\Promise\PromiseInterface {
if (file_exists($fileName)) {
$filesystem->unlink($fileName);
}







$getFolderContent = static function ($dir): array {
$finder = Finder::create()
->ignoreVCS(false)
->ignoreDotFiles(false)
->notName('.DS_Store')
->depth(0)
->in($dir);

return iterator_to_array($finder);
};
$renameRecursively = null;











$renameRecursively = static function ($from, $to) use ($filesystem, $getFolderContent, $package, &$renameRecursively) {
$contentDir = $getFolderContent($from);


foreach ($contentDir as $file) {
$file = (string) $file;
if (is_dir($to . '/' . basename($file))) {
if (!is_dir($file)) {
throw new \RuntimeException('Installing '.$package.' would lead to overwriting the '.$to.'/'.basename($file).' directory with a file from the package, invalid operation.');
}
$renameRecursively($file, $to . '/' . basename($file));
} else {
$filesystem->rename($file, $to . '/' . basename($file));
}
}
};

$renameAsOne = false;
if (!file_exists($path)) {
$renameAsOne = true;
} elseif ($filesystem->isDirEmpty($path)) {
try {
if ($filesystem->removeDirectoryPhp($path)) {
$renameAsOne = true;
}
} catch (\RuntimeException $e) {

}
}

$contentDir = $getFolderContent($temporaryDir);
$singleDirAtTopLevel = 1 === count($contentDir) && is_dir((string) reset($contentDir));

if ($renameAsOne) {

if ($singleDirAtTopLevel) {
$extractedDir = (string) reset($contentDir);
} else {
$extractedDir = $temporaryDir;
}
$filesystem->rename($extractedDir, $path);
} else {

$from = $temporaryDir;
if ($singleDirAtTopLevel) {
$from = (string) reset($contentDir);
}

$renameRecursively($from, $path);
}

$promise = $filesystem->removeDirectoryAsync($temporaryDir);

return $promise->then(function () use ($package, $path, $temporaryDir) {
$this->removeCleanupPath($package, $temporaryDir);
$this->removeCleanupPath($package, $path);
});
}, static function ($e) use ($cleanup) {
$cleanup();

throw $e;
});
}




protected function getInstallOperationAppendix(PackageInterface $package, string $path): string
{
return ': Extracting archive';
}









abstract protected function extract(PackageInterface $package, string $file, string $path): PromiseInterface;
}
