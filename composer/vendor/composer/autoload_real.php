<?php



class ComposerAutoloaderInitComposerPhar1676031832
{
private static $loader;

public static function loadClassLoader($class)
{
if ('Composer\Autoload\ClassLoader' === $class) {
require __DIR__ . '/ClassLoader.php';
}
}




public static function getLoader()
{
if (null !== self::$loader) {
return self::$loader;
}

spl_autoload_register(array('ComposerAutoloaderInitComposerPhar1676031832', 'loadClassLoader'), true, true);
self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
spl_autoload_unregister(array('ComposerAutoloaderInitComposerPhar1676031832', 'loadClassLoader'));

require __DIR__ . '/autoload_static.php';
call_user_func(\Composer\Autoload\ComposerStaticInitComposerPhar1676031832::getInitializer($loader));

$loader->register(true);

$filesToLoad = \Composer\Autoload\ComposerStaticInitComposerPhar1676031832::$files;
$requireFile = \Closure::bind(static function ($fileIdentifier, $file) {
if (empty($GLOBALS['__composer_autoload_files'][$fileIdentifier])) {
$GLOBALS['__composer_autoload_files'][$fileIdentifier] = true;

require $file;
}
}, null, null);
foreach ($filesToLoad as $fileIdentifier => $file) {
$requireFile($fileIdentifier, $file);
}

return $loader;
}
}
