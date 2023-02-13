<?php declare(strict_types=1);











namespace Composer\Json;

use Composer\Pcre\Preg;











class JsonFormatter
{









public static function format(string $json, bool $unescapeUnicode, bool $unescapeSlashes): string
{
$result = '';
$pos = 0;
$strLen = strlen($json);
$indentStr = '    ';
$newLine = "\n";
$outOfQuotes = true;
$buffer = '';
$noescape = true;

for ($i = 0; $i < $strLen; $i++) {

$char = substr($json, $i, 1);


if ('"' === $char && $noescape) {
$outOfQuotes = !$outOfQuotes;
}

if (!$outOfQuotes) {
$buffer .= $char;
$noescape = '\\' === $char ? !$noescape : true;
continue;
}
if ('' !== $buffer) {
if ($unescapeSlashes) {
$buffer = str_replace('\\/', '/', $buffer);
}

if ($unescapeUnicode && function_exists('mb_convert_encoding')) {

$buffer = Preg::replaceCallback('/(\\\\+)u([0-9a-f]{4})/i', static function ($match) {
assert(is_string($match[1]));
assert(is_string($match[2]));
$l = strlen($match[1]);

if ($l % 2) {
$code = hexdec($match[2]);


if (0xD800 <= $code && 0xDFFF >= $code) {
return $match[0];
}

return str_repeat('\\', $l - 1) . mb_convert_encoding(
pack('H*', $match[2]),
'UTF-8',
'UCS-2BE'
);
}

return $match[0];
}, $buffer);
}

$result .= $buffer.$char;
$buffer = '';
continue;
}

if (':' === $char) {

$char .= ' ';
} elseif ('}' === $char || ']' === $char) {
$pos--;
$prevChar = substr($json, $i - 1, 1);

if ('{' !== $prevChar && '[' !== $prevChar) {


$result .= $newLine;
$result .= str_repeat($indentStr, $pos);
} else {

$result = rtrim($result);
}
}

$result .= $char;



if (',' === $char || '{' === $char || '[' === $char) {
$result .= $newLine;

if ('{' === $char || '[' === $char) {
$pos++;
}

$result .= str_repeat($indentStr, $pos);
}
}

return $result;
}
}
