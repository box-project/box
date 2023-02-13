<?php










use Symfony\Polyfill\Php81 as p;

if (\PHP_VERSION_ID >= 80100) {
return;
}

if (defined('MYSQLI_REFRESH_SLAVE') && !defined('MYSQLI_REFRESH_REPLICA')) {
define('MYSQLI_REFRESH_REPLICA', 64);
}

if (!function_exists('array_is_list')) {
function array_is_list(array $array): bool { return p\Php81::array_is_list($array); }
}

if (!function_exists('enum_exists')) {
function enum_exists(string $enum, bool $autoload = true): bool { return $autoload && class_exists($enum) && false; }
}
