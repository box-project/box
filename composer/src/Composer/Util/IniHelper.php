<?php declare(strict_types=1);











namespace Composer\Util;

use Composer\XdebugHandler\XdebugHandler;








class IniHelper
{








public static function getAll(): array
{
return XdebugHandler::getAllIniFiles();
}




public static function getMessage(): string
{
$paths = self::getAll();

if (empty($paths[0])) {
array_shift($paths);
}

$ini = array_shift($paths);

if (empty($ini)) {
return 'A php.ini file does not exist. You will have to create one.';
}

if (!empty($paths)) {
return 'Your command-line PHP is using multiple ini files. Run `php --ini` to show them.';
}

return 'The php.ini used by your command-line PHP is: '.$ini;
}
}
