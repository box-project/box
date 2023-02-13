<?php declare(strict_types=1);

















namespace Composer\ClassMapGenerator;

use Composer\Pcre\Preg;
use Symfony\Component\Finder\Finder;
use Composer\IO\IOInterface;







class ClassMapGenerator
{



private $extensions;




private $scannedFiles = null;




private $classMap;




public function __construct(array $extensions = ['php', 'inc'])
{
$this->extensions = $extensions;
$this->classMap = new ClassMap;
}








public function avoidDuplicateScans(FileList $scannedFiles = null): self
{
$this->scannedFiles = $scannedFiles ?? new FileList;

return $this;
}









public static function createMap($path): array
{
$generator = new self();

$generator->scanPaths($path);

return $generator->getClassMap()->getMap();
}

public function getClassMap(): ClassMap
{
return $this->classMap;
}











public function scanPaths($path, string $excluded = null, string $autoloadType = 'classmap', ?string $namespace = null): void
{
if (!in_array($autoloadType, ['psr-0', 'psr-4', 'classmap'], true)) {
throw new \InvalidArgumentException('$autoloadType must be one of: "psr-0", "psr-4" or "classmap"');
}

if ('classmap' !== $autoloadType) {
if (!is_string($path)) {
throw new \InvalidArgumentException('$path must be a string when specifying a psr-0 or psr-4 autoload type');
}
if (!is_string($namespace)) {
throw new \InvalidArgumentException('$namespace must be given (even if it is an empty string if you do not want to filter) when specifying a psr-0 or psr-4 autoload type');
}
$basePath = $path;
}

if (is_string($path)) {
if (is_file($path)) {
$path = [new \SplFileInfo($path)];
} elseif (is_dir($path) || strpos($path, '*') !== false) {
$path = Finder::create()
->files()
->followLinks()
->name('/\.(?:'.implode('|', array_map('preg_quote', $this->extensions)).')$/')
->in($path);
} else {
throw new \RuntimeException(
'Could not scan for classes inside "'.$path.'" which does not appear to be a file nor a folder'
);
}
}

$cwd = realpath(self::getCwd());

foreach ($path as $file) {
$filePath = $file->getPathname();
if (!in_array(pathinfo($filePath, PATHINFO_EXTENSION), $this->extensions, true)) {
continue;
}

if (!self::isAbsolutePath($filePath)) {
$filePath = $cwd . '/' . $filePath;
$filePath = self::normalizePath($filePath);
} else {
$filePath = Preg::replace('{[\\\\/]{2,}}', '/', $filePath);
}

if ('' === $filePath) {
throw new \LogicException('Got an empty $filePath for '.$file->getPathname());
}

$realPath = realpath($filePath);


if (false === $realPath) {
throw new \RuntimeException('realpath of '.$filePath.' failed to resolve, got false');
}



if ($this->scannedFiles !== null && $this->scannedFiles->contains($realPath)) {
continue;
}


if (null !== $excluded && Preg::isMatch($excluded, strtr($realPath, '\\', '/'))) {
continue;
}

if (null !== $excluded && Preg::isMatch($excluded, strtr($filePath, '\\', '/'))) {
continue;
}

$classes = PhpFileParser::findClasses($filePath);
if ('classmap' !== $autoloadType && isset($namespace, $basePath)) {
$classes = $this->filterByNamespace($classes, $filePath, $namespace, $autoloadType, $basePath);


if (\count($classes) > 0 && $this->scannedFiles !== null) {
$this->scannedFiles->add($realPath);
}
} elseif ($this->scannedFiles !== null) {

$this->scannedFiles->add($realPath);
}

foreach ($classes as $class) {
if (!$this->classMap->hasClass($class)) {
$this->classMap->addClass($class, $filePath);
} elseif ($filePath !== $this->classMap->getClassPath($class) && !Preg::isMatch('{/(test|fixture|example|stub)s?/}i', strtr($this->classMap->getClassPath($class).' '.$filePath, '\\', '/'))) {
$this->classMap->addAmbiguousClass($class, $filePath);
}
}
}
}











private function filterByNamespace(array $classes, string $filePath, string $baseNamespace, string $namespaceType, string $basePath): array
{
$validClasses = [];
$rejectedClasses = [];

$realSubPath = substr($filePath, strlen($basePath) + 1);
$dotPosition = strrpos($realSubPath, '.');
$realSubPath = substr($realSubPath, 0, $dotPosition === false ? PHP_INT_MAX : $dotPosition);

foreach ($classes as $class) {

if ('' !== $baseNamespace && 0 !== strpos($class, $baseNamespace)) {
continue;
}

if ('psr-0' === $namespaceType) {
$namespaceLength = strrpos($class, '\\');
if (false !== $namespaceLength) {
$namespace = substr($class, 0, $namespaceLength + 1);
$className = substr($class, $namespaceLength + 1);
$subPath = str_replace('\\', DIRECTORY_SEPARATOR, $namespace)
. str_replace('_', DIRECTORY_SEPARATOR, $className);
} else {
$subPath = str_replace('_', DIRECTORY_SEPARATOR, $class);
}
} elseif ('psr-4' === $namespaceType) {
$subNamespace = ('' !== $baseNamespace) ? substr($class, strlen($baseNamespace)) : $class;
$subPath = str_replace('\\', DIRECTORY_SEPARATOR, $subNamespace);
} else {
throw new \InvalidArgumentException('$namespaceType must be "psr-0" or "psr-4"');
}
if ($subPath === $realSubPath) {
$validClasses[] = $class;
} else {
$rejectedClasses[] = $class;
}
}

if (\count($validClasses) === 0) {
foreach ($rejectedClasses as $class) {
$this->classMap->addPsrViolation("Class $class located in ".Preg::replace('{^'.preg_quote(self::getCwd()).'}', '.', $filePath, 1)." does not comply with $namespaceType autoloading standard. Skipping.");
}

return [];
}

return $validClasses;
}









private static function isAbsolutePath(string $path)
{
return strpos($path, '/') === 0 || substr($path, 1, 1) === ':' || strpos($path, '\\\\') === 0;
}










private static function normalizePath(string $path)
{
$parts = [];
$path = strtr($path, '\\', '/');
$prefix = '';
$absolute = '';


if (strpos($path, '//') === 0 && \strlen($path) > 2) {
$absolute = '//';
$path = substr($path, 2);
}


if (Preg::isMatch('{^( [0-9a-z]{2,}+: (?: // (?: [a-z]: )? )? | [a-z]: )}ix', $path, $match)) {
$prefix = $match[1];
$path = substr($path, \strlen($prefix));
}

if (strpos($path, '/') === 0) {
$absolute = '/';
$path = substr($path, 1);
}

$up = false;
foreach (explode('/', $path) as $chunk) {
if ('..' === $chunk && (\strlen($absolute) > 0 || $up)) {
array_pop($parts);
$up = !(\count($parts) === 0 || '..' === end($parts));
} elseif ('.' !== $chunk && '' !== $chunk) {
$parts[] = $chunk;
$up = '..' !== $chunk;
}
}


$prefix = Preg::replaceCallback('{(^|://)[a-z]:$}i', function (array $m) { return strtoupper($m[0]); }, $prefix);

return $prefix.$absolute.implode('/', $parts);
}




private static function getCwd(): string
{
$cwd = getcwd();

if (false === $cwd) {
throw new \RuntimeException('Could not determine the current working directory');
}

return $cwd;
}
}
