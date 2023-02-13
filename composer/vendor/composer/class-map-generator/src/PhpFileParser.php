<?php declare(strict_types=1);











namespace Composer\ClassMapGenerator;

use Composer\Pcre\Preg;




class PhpFileParser
{







public static function findClasses(string $path): array
{
$extraTypes = self::getExtraTypes();



$contents = @php_strip_whitespace($path);
if ('' === $contents) {
if (!file_exists($path)) {
$message = 'File at "%s" does not exist, check your classmap definitions';
} elseif (!self::isReadable($path)) {
$message = 'File at "%s" is not readable, check its permissions';
} elseif ('' === trim((string) file_get_contents($path))) {

return array();
} else {
$message = 'File at "%s" could not be parsed as PHP, it may be binary or corrupted';
}
$error = error_get_last();
if (isset($error['message'])) {
$message .= PHP_EOL . 'The following message may be helpful:' . PHP_EOL . $error['message'];
}
throw new \RuntimeException(sprintf($message, $path));
}


Preg::matchAll('{\b(?:class|interface|trait'.$extraTypes.')\s}i', $contents, $matches);
if (!$matches) {
return array();
}

$p = new PhpFileCleaner($contents, count($matches[0]));
$contents = $p->clean();
unset($p);

Preg::matchAll('{
            (?:
                 \b(?<![\$:>])(?P<type>class|interface|trait'.$extraTypes.') \s++ (?P<name>[a-zA-Z_\x7f-\xff:][a-zA-Z0-9_\x7f-\xff:\-]*+)
               | \b(?<![\$:>])(?P<ns>namespace) (?P<nsname>\s++[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\s*+\\\\\s*+[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)*+)? \s*+ [\{;]
            )
        }ix', $contents, $matches);

$classes = array();
$namespace = '';

for ($i = 0, $len = count($matches['type']); $i < $len; $i++) {
if (isset($matches['ns'][$i]) && $matches['ns'][$i] !== '') {
$namespace = str_replace(array(' ', "\t", "\r", "\n"), '', (string) $matches['nsname'][$i]) . '\\';
} else {
$name = $matches['name'][$i];

if ($name === 'extends' || $name === 'implements') {
continue;
}
if ($name[0] === ':') {

$name = 'xhp'.substr(str_replace(array('-', ':'), array('_', '__'), $name), 1);
} elseif (strtolower($matches['type'][$i]) === 'enum') {








$colonPos = strrpos($name, ':');
if (false !== $colonPos) {
$name = substr($name, 0, $colonPos);
}
}
$classes[] = ltrim($namespace . $name, '\\');
}
}

return $classes;
}




private static function getExtraTypes(): string
{
static $extraTypes = null;

if (null === $extraTypes) {
$extraTypes = '';
if (PHP_VERSION_ID >= 80100 || (defined('HHVM_VERSION') && version_compare(HHVM_VERSION, '3.3', '>='))) {
$extraTypes .= '|enum';
}

PhpFileCleaner::setTypeConfig(array_merge(['class', 'interface', 'trait'], array_filter(explode('|', $extraTypes))));
}

return $extraTypes;
}












private static function isReadable(string $path)
{
if (is_readable($path)) {
return true;
}

if (is_file($path)) {
return false !== @file_get_contents($path, false, null, 0, 1);
}


return false;
}
}
