<?php declare(strict_types=1);











namespace Composer;

use Composer\IO\IOInterface;
use Composer\Pcre\Preg;
use Composer\Util\Filesystem;
use Composer\Util\Platform;
use Composer\Util\Silencer;
use Symfony\Component\Finder\Finder;






class Cache
{

private static $cacheCollected = null;

private $io;

private $root;

private $enabled = null;

private $allowlist;

private $filesystem;

private $readOnly;







public function __construct(IOInterface $io, string $cacheDir, string $allowlist = 'a-z0-9._', ?Filesystem $filesystem = null, bool $readOnly = false)
{
$this->io = $io;
$this->root = rtrim($cacheDir, '/\\') . '/';
$this->allowlist = $allowlist;
$this->filesystem = $filesystem ?: new Filesystem();
$this->readOnly = (bool) $readOnly;

if (!self::isUsable($cacheDir)) {
$this->enabled = false;
}
}




public function setReadOnly(bool $readOnly)
{
$this->readOnly = (bool) $readOnly;
}




public function isReadOnly()
{
return $this->readOnly;
}




public static function isUsable(string $path)
{
return !Preg::isMatch('{(^|[\\\\/])(\$null|nul|NUL|/dev/null)([\\\\/]|$)}', $path);
}




public function isEnabled()
{
if ($this->enabled === null) {
$this->enabled = true;

if (
!$this->readOnly
&& (
(!is_dir($this->root) && !Silencer::call('mkdir', $this->root, 0777, true))
|| !is_writable($this->root)
)
) {
$this->io->writeError('<warning>Cannot create cache directory ' . $this->root . ', or directory is not writable. Proceeding without cache. See also cache-read-only config if your filesystem is read-only.</warning>');
$this->enabled = false;
}
}

return $this->enabled;
}




public function getRoot()
{
return $this->root;
}




public function read(string $file)
{
if ($this->isEnabled()) {
$file = Preg::replace('{[^'.$this->allowlist.']}i', '-', $file);
if (file_exists($this->root . $file)) {
$this->io->writeError('Reading '.$this->root . $file.' from cache', true, IOInterface::DEBUG);

return file_get_contents($this->root . $file);
}
}

return false;
}




public function write(string $file, string $contents)
{
$wasEnabled = $this->enabled === true;

if ($this->isEnabled() && !$this->readOnly) {
$file = Preg::replace('{[^'.$this->allowlist.']}i', '-', $file);

$this->io->writeError('Writing '.$this->root . $file.' into cache', true, IOInterface::DEBUG);

$tempFileName = $this->root . $file . uniqid('.', true) . '.tmp';
try {
return file_put_contents($tempFileName, $contents) !== false && rename($tempFileName, $this->root . $file);
} catch (\ErrorException $e) {


if ($wasEnabled) {
clearstatcache();
$this->enabled = null;
return $this->write($file, $contents);
}

$this->io->writeError('<warning>Failed to write into cache: '.$e->getMessage().'</warning>', true, IOInterface::DEBUG);
if (Preg::isMatch('{^file_put_contents\(\): Only ([0-9]+) of ([0-9]+) bytes written}', $e->getMessage(), $m)) {

unlink($tempFileName);

$message = sprintf(
'<warning>Writing %1$s into cache failed after %2$u of %3$u bytes written, only %4$s bytes of free space available</warning>',
$tempFileName,
$m[1],
$m[2],
function_exists('disk_free_space') ? @disk_free_space(dirname($tempFileName)) : 'unknown'
);

$this->io->writeError($message);

return false;
}

throw $e;
}
}

return false;
}






public function copyFrom(string $file, string $source)
{
if ($this->isEnabled() && !$this->readOnly) {
$file = Preg::replace('{[^'.$this->allowlist.']}i', '-', $file);
$this->filesystem->ensureDirectoryExists(dirname($this->root . $file));

if (!file_exists($source)) {
$this->io->writeError('<error>'.$source.' does not exist, can not write into cache</error>');
} elseif ($this->io->isDebug()) {
$this->io->writeError('Writing '.$this->root . $file.' into cache from '.$source);
}

return copy($source, $this->root . $file);
}

return false;
}






public function copyTo(string $file, string $target)
{
if ($this->isEnabled()) {
$file = Preg::replace('{[^'.$this->allowlist.']}i', '-', $file);
if (file_exists($this->root . $file)) {
try {
touch($this->root . $file, (int) filemtime($this->root . $file), time());
} catch (\ErrorException $e) {


Silencer::call('touch', $this->root . $file);
}

$this->io->writeError('Reading '.$this->root . $file.' from cache', true, IOInterface::DEBUG);

return copy($this->root . $file, $target);
}
}

return false;
}




public function gcIsNecessary()
{
if (self::$cacheCollected) {
return false;
}

self::$cacheCollected = true;
if (Platform::getEnv('COMPOSER_TEST_SUITE')) {
return false;
}

if (Platform::isInputCompletionProcess()) {
return false;
}

return !random_int(0, 50);
}




public function remove(string $file)
{
if ($this->isEnabled() && !$this->readOnly) {
$file = Preg::replace('{[^'.$this->allowlist.']}i', '-', $file);
if (file_exists($this->root . $file)) {
return $this->filesystem->unlink($this->root . $file);
}
}

return false;
}




public function clear()
{
if ($this->isEnabled() && !$this->readOnly) {
$this->filesystem->emptyDirectory($this->root);

return true;
}

return false;
}





public function getAge(string $file)
{
if ($this->isEnabled()) {
$file = Preg::replace('{[^'.$this->allowlist.']}i', '-', $file);
if (file_exists($this->root . $file) && ($mtime = filemtime($this->root . $file)) !== false) {
return abs(time() - $mtime);
}
}

return false;
}




public function gc(int $ttl, int $maxSize)
{
if ($this->isEnabled() && !$this->readOnly) {
$expire = new \DateTime();
$expire->modify('-'.$ttl.' seconds');

$finder = $this->getFinder()->date('until '.$expire->format('Y-m-d H:i:s'));
foreach ($finder as $file) {
$this->filesystem->unlink($file->getPathname());
}

$totalSize = $this->filesystem->size($this->root);
if ($totalSize > $maxSize) {
$iterator = $this->getFinder()->sortByAccessedTime()->getIterator();
while ($totalSize > $maxSize && $iterator->valid()) {
$filepath = $iterator->current()->getPathname();
$totalSize -= $this->filesystem->size($filepath);
$this->filesystem->unlink($filepath);
$iterator->next();
}
}

self::$cacheCollected = true;

return true;
}

return false;
}

public function gcVcsCache(int $ttl): bool
{
if ($this->isEnabled()) {
$expire = new \DateTime();
$expire->modify('-'.$ttl.' seconds');

$finder = Finder::create()->in($this->root)->directories()->depth(0)->date('until '.$expire->format('Y-m-d H:i:s'));
foreach ($finder as $file) {
$this->filesystem->removeDirectory($file->getPathname());
}

self::$cacheCollected = true;

return true;
}

return false;
}




public function sha1(string $file)
{
if ($this->isEnabled()) {
$file = Preg::replace('{[^'.$this->allowlist.']}i', '-', $file);
if (file_exists($this->root . $file)) {
return sha1_file($this->root . $file);
}
}

return false;
}




public function sha256(string $file)
{
if ($this->isEnabled()) {
$file = Preg::replace('{[^'.$this->allowlist.']}i', '-', $file);
if (file_exists($this->root . $file)) {
return hash_file('sha256', $this->root . $file);
}
}

return false;
}




protected function getFinder()
{
return Finder::create()->in($this->root)->files();
}
}
