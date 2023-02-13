<?php declare(strict_types=1);











namespace Composer\Console;

use Closure;
use Composer\Pcre\Preg;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;




class HtmlOutputFormatter extends OutputFormatter
{

private static $availableForegroundColors = [
30 => 'black',
31 => 'red',
32 => 'green',
33 => 'yellow',
34 => 'blue',
35 => 'magenta',
36 => 'cyan',
37 => 'white',
];

private static $availableBackgroundColors = [
40 => 'black',
41 => 'red',
42 => 'green',
43 => 'yellow',
44 => 'blue',
45 => 'magenta',
46 => 'cyan',
47 => 'white',
];

private static $availableOptions = [
1 => 'bold',
4 => 'underscore',



];




public function __construct(array $styles = [])
{
parent::__construct(true, $styles);
}

public function format(?string $message): ?string
{
$formatted = parent::format($message);

if ($formatted === null) {
return null;
}

$clearEscapeCodes = '(?:39|49|0|22|24|25|27|28)';

return Preg::replaceCallback("{\033\[([0-9;]+)m(.*?)\033\[(?:".$clearEscapeCodes.";)*?".$clearEscapeCodes."m}s", Closure::fromCallable([$this, 'formatHtml']), $formatted);
}




private function formatHtml(array $matches): string
{
assert(is_string($matches[1]));
$out = '<span style="';
foreach (explode(';', $matches[1]) as $code) {
if (isset(self::$availableForegroundColors[(int) $code])) {
$out .= 'color:'.self::$availableForegroundColors[(int) $code].';';
} elseif (isset(self::$availableBackgroundColors[(int) $code])) {
$out .= 'background-color:'.self::$availableBackgroundColors[(int) $code].';';
} elseif (isset(self::$availableOptions[(int) $code])) {
switch (self::$availableOptions[(int) $code]) {
case 'bold':
$out .= 'font-weight:bold;';
break;

case 'underscore':
$out .= 'text-decoration:underline;';
break;
}
}
}

return $out.'">'.$matches[2].'</span>';
}
}
