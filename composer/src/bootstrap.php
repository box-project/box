<?php declare(strict_types=1);











use Composer\Autoload\ClassLoader;

function includeIfExists(string $file): ?ClassLoader
{
return file_exists($file) ? include $file : null;
}

if ((!$loader = includeIfExists(__DIR__.'/../vendor/autoload.php')) && (!$loader = includeIfExists(__DIR__.'/../../../autoload.php'))) {
echo 'You must set up the project dependencies using `composer install`'.PHP_EOL.
'See https://getcomposer.org/download/ for instructions on installing Composer'.PHP_EOL;
exit(1);
}

return $loader;
