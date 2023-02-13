<?php declare(strict_types=1);











namespace Composer\ClassMapGenerator;




class ClassMap implements \Countable
{



public $map = [];




private $ambiguousClasses = [];




private $psrViolations = [];






public function getMap(): array
{
return $this->map;
}












public function getPsrViolations(): array
{
return $this->psrViolations;
}










public function getAmbiguousClasses(): array
{
return $this->ambiguousClasses;
}




public function sort(): void
{
ksort($this->map);
}





public function addClass(string $className, string $path): void
{
$this->map[$className] = $path;
}





public function getClassPath(string $className): string
{
if (!isset($this->map[$className])) {
throw new \OutOfBoundsException('Class '.$className.' is not present in the map');
}

return $this->map[$className];
}




public function hasClass(string $className): bool
{
return isset($this->map[$className]);
}

public function addPsrViolation(string $warning): void
{
$this->psrViolations[] = $warning;
}





public function addAmbiguousClass(string $className, string $path): void
{
$this->ambiguousClasses[$className][] = $path;
}

public function count(): int
{
return \count($this->map);
}
}
