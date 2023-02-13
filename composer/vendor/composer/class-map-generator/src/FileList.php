<?php declare(strict_types=1);











namespace Composer\ClassMapGenerator;






class FileList
{



public $files = [];




public function add(string $path): void
{
$this->files[$path] = true;
}




public function contains(string $path): bool
{
return isset($this->files[$path]);
}
}
