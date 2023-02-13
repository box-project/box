<?php declare(strict_types=1);











namespace Composer\Platform;

class Runtime
{



public function hasConstant(string $constant, ?string $class = null): bool
{
return defined(ltrim($class.'::'.$constant, ':'));
}






public function getConstant(string $constant, ?string $class = null)
{
return constant(ltrim($class.'::'.$constant, ':'));
}

public function hasFunction(string $fn): bool
{
return function_exists($fn);
}






public function invoke(callable $callable, array $arguments = [])
{
return $callable(...$arguments);
}




public function hasClass(string $class): bool
{
return class_exists($class, false);
}







public function construct(string $class, array $arguments = []): object
{
if (empty($arguments)) {
return new $class;
}

$refl = new \ReflectionClass($class);

return $refl->newInstanceArgs($arguments);
}


public function getExtensions(): array
{
return get_loaded_extensions();
}

public function getExtensionVersion(string $extension): string
{
$version = phpversion($extension);
if ($version === false) {
$version = '0';
}

return $version;
}




public function getExtensionInfo(string $extension): string
{
$reflector = new \ReflectionExtension($extension);

ob_start();
$reflector->info();

return ob_get_clean();
}
}
