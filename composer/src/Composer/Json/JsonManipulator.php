<?php declare(strict_types=1);











namespace Composer\Json;

use Composer\Pcre\Preg;
use Composer\Repository\PlatformRepository;




class JsonManipulator
{

private const DEFINES = '(?(DEFINE)
       (?<number>    -? (?= [1-9]|0(?!\d) ) \d++ (?:\.\d++)? (?:[eE] [+-]?+ \d++)? )
       (?<boolean>   true | false | null )
       (?<string>    " (?:[^"\\\\]*+ | \\\\ ["\\\\bfnrt\/] | \\\\ u [0-9A-Fa-f]{4} )* " )
       (?<array>     \[  (?:  (?&json) \s*+ (?: , (?&json) \s*+ )*+  )?+  \s*+ \] )
       (?<pair>      \s*+ (?&string) \s*+ : (?&json) \s*+ )
       (?<object>    \{  (?:  (?&pair)  (?: , (?&pair)  )*+  )?+  \s*+ \} )
       (?<json>      \s*+ (?: (?&number) | (?&boolean) | (?&string) | (?&array) | (?&object) ) )
    )';


private $contents;

private $newline;

private $indent;

public function __construct(string $contents)
{
$contents = trim($contents);
if ($contents === '') {
$contents = '{}';
}
if (!Preg::isMatch('#^\{(.*)\}$#s', $contents)) {
throw new \InvalidArgumentException('The json file must be an object ({})');
}
$this->newline = false !== strpos($contents, "\r\n") ? "\r\n" : "\n";
$this->contents = $contents === '{}' ? '{' . $this->newline . '}' : $contents;
$this->detectIndenting();
}

public function getContents(): string
{
return $this->contents . $this->newline;
}

public function addLink(string $type, string $package, string $constraint, bool $sortPackages = false): bool
{
$decoded = JsonFile::parseJson($this->contents);


if (!isset($decoded[$type])) {
return $this->addMainKey($type, [$package => $constraint]);
}

$regex = '{'.self::DEFINES.'^(?P<start>\s*\{\s*(?:(?&string)\s*:\s*(?&json)\s*,\s*)*?)'.
'(?P<property>'.preg_quote(JsonFile::encode($type)).'\s*:\s*)(?P<value>(?&json))(?P<end>.*)}sx';
if (!Preg::isMatch($regex, $this->contents, $matches)) {
return false;
}
assert(is_string($matches['start']));
assert(is_string($matches['value']));
assert(is_string($matches['end']));

$links = $matches['value'];


$packageRegex = str_replace('/', '\\\\?/', preg_quote($package));
$regex = '{'.self::DEFINES.'"(?P<package>'.$packageRegex.')"(\s*:\s*)(?&string)}ix';
if (Preg::isMatch($regex, $links, $packageMatches)) {
assert(is_string($packageMatches['package']));

$existingPackage = $packageMatches['package'];
$packageRegex = str_replace('/', '\\\\?/', preg_quote($existingPackage));
$links = Preg::replaceCallback('{'.self::DEFINES.'"'.$packageRegex.'"(?P<separator>\s*:\s*)(?&string)}ix', static function ($m) use ($existingPackage, $constraint): string {
return JsonFile::encode(str_replace('\\/', '/', $existingPackage)) . $m['separator'] . '"' . $constraint . '"';
}, $links);
} else {
if (Preg::isMatchStrictGroups('#^\s*\{\s*\S+.*?(\s*\}\s*)$#s', $links, $match)) {

$links = Preg::replace(
'{'.preg_quote($match[1]).'$}',

addcslashes(',' . $this->newline . $this->indent . $this->indent . JsonFile::encode($package).': '.JsonFile::encode($constraint) . $match[1], '\\$'),
$links
);
} else {

$links = '{' . $this->newline .
$this->indent . $this->indent . JsonFile::encode($package).': '.JsonFile::encode($constraint) . $this->newline .
$this->indent . '}';
}
}

if (true === $sortPackages) {
$requirements = json_decode($links, true);
$this->sortPackages($requirements);
$links = $this->format($requirements);
}

$this->contents = $matches['start'] . $matches['property'] . $links . $matches['end'];

return true;
}








private function sortPackages(array &$packages = []): void
{
$prefix = static function ($requirement): string {
if (PlatformRepository::isPlatformPackage($requirement)) {
return Preg::replace(
[
'/^php/',
'/^hhvm/',
'/^ext/',
'/^lib/',
'/^\D/',
],
[
'0-$0',
'1-$0',
'2-$0',
'3-$0',
'4-$0',
],
$requirement
);
}

return '5-'.$requirement;
};

uksort($packages, static function ($a, $b) use ($prefix): int {
return strnatcmp($prefix($a), $prefix($b));
});
}




public function addRepository(string $name, $config, bool $append = true): bool
{
return $this->addSubNode('repositories', $name, $config, $append);
}

public function removeRepository(string $name): bool
{
return $this->removeSubNode('repositories', $name);
}




public function addConfigSetting(string $name, $value): bool
{
return $this->addSubNode('config', $name, $value);
}

public function removeConfigSetting(string $name): bool
{
return $this->removeSubNode('config', $name);
}




public function addProperty(string $name, $value): bool
{
if (strpos($name, 'suggest.') === 0) {
return $this->addSubNode('suggest', substr($name, 8), $value);
}

if (strpos($name, 'extra.') === 0) {
return $this->addSubNode('extra', substr($name, 6), $value);
}

if (strpos($name, 'scripts.') === 0) {
return $this->addSubNode('scripts', substr($name, 8), $value);
}

return $this->addMainKey($name, $value);
}

public function removeProperty(string $name): bool
{
if (strpos($name, 'suggest.') === 0) {
return $this->removeSubNode('suggest', substr($name, 8));
}

if (strpos($name, 'extra.') === 0) {
return $this->removeSubNode('extra', substr($name, 6));
}

if (strpos($name, 'scripts.') === 0) {
return $this->removeSubNode('scripts', substr($name, 8));
}

return $this->removeMainKey($name);
}




public function addSubNode(string $mainNode, string $name, $value, bool $append = true): bool
{
$decoded = JsonFile::parseJson($this->contents);

$subName = null;
if (in_array($mainNode, ['config', 'extra', 'scripts']) && false !== strpos($name, '.')) {
[$name, $subName] = explode('.', $name, 2);
}


if (!isset($decoded[$mainNode])) {
if ($subName !== null) {
$this->addMainKey($mainNode, [$name => [$subName => $value]]);
} else {
$this->addMainKey($mainNode, [$name => $value]);
}

return true;
}


$nodeRegex = '{'.self::DEFINES.'^(?P<start> \s* \{ \s* (?: (?&string) \s* : (?&json) \s* , \s* )*?'.
preg_quote(JsonFile::encode($mainNode)).'\s*:\s*)(?P<content>(?&object))(?P<end>.*)}sx';

try {
if (!Preg::isMatch($nodeRegex, $this->contents, $match)) {
return false;
}
} catch (\RuntimeException $e) {
if ($e->getCode() === PREG_BACKTRACK_LIMIT_ERROR) {
return false;
}
throw $e;
}

assert(is_string($match['start']));
assert(is_string($match['content']));
assert(is_string($match['end']));

$children = $match['content'];

if (!@json_decode($children)) {
return false;
}


$childRegex = '{'.self::DEFINES.'(?P<start>"'.preg_quote($name).'"\s*:\s*)(?P<content>(?&json))(?P<end>,?)}x';
if (Preg::isMatch($childRegex, $children, $matches)) {
$children = Preg::replaceCallback($childRegex, function ($matches) use ($subName, $value): string {
if ($subName !== null && is_string($matches['content'])) {
$curVal = json_decode($matches['content'], true);
if (!is_array($curVal)) {
$curVal = [];
}
$curVal[$subName] = $value;
$value = $curVal;
}

return $matches['start'] . $this->format($value, 1) . $matches['end'];
}, $children);
} else {
Preg::match('#^{ (?P<leadingspace>\s*?) (?P<content>\S+.*?)? (?P<trailingspace>\s*) }$#sx', $children, $match);

$whitespace = '';
if (!empty($match['trailingspace'])) {
$whitespace = $match['trailingspace'];
}

if (!empty($match['content'])) {
if ($subName !== null) {
$value = [$subName => $value];
}


if ($append) {
$children = Preg::replace(
'#'.$whitespace.'}$#',
addcslashes(',' . $this->newline . $this->indent . $this->indent . JsonFile::encode($name).': '.$this->format($value, 1) . $whitespace . '}', '\\$'),
$children
);
} else {
$whitespace = '';
if (!empty($match['leadingspace'])) {
$whitespace = $match['leadingspace'];
}

$children = Preg::replace(
'#^{'.$whitespace.'#',
addcslashes('{' . $whitespace . JsonFile::encode($name).': '.$this->format($value, 1) . ',' . $this->newline . $this->indent . $this->indent, '\\$'),
$children
);
}
} else {
if ($subName !== null) {
$value = [$subName => $value];
}


$children = '{' . $this->newline . $this->indent . $this->indent . JsonFile::encode($name).': '.$this->format($value, 1) . $whitespace . '}';
}
}

$this->contents = Preg::replaceCallback($nodeRegex, static function ($m) use ($children): string {
return $m['start'] . $children . $m['end'];
}, $this->contents);

return true;
}

public function removeSubNode(string $mainNode, string $name): bool
{
$decoded = JsonFile::parseJson($this->contents);


if (empty($decoded[$mainNode])) {
return true;
}


$nodeRegex = '{'.self::DEFINES.'^(?P<start> \s* \{ \s* (?: (?&string) \s* : (?&json) \s* , \s* )*?'.
preg_quote(JsonFile::encode($mainNode)).'\s*:\s*)(?P<content>(?&object))(?P<end>.*)}sx';
try {
if (!Preg::isMatch($nodeRegex, $this->contents, $match)) {
return false;
}
} catch (\RuntimeException $e) {
if ($e->getCode() === PREG_BACKTRACK_LIMIT_ERROR) {
return false;
}
throw $e;
}

assert(is_string($match['start']));
assert(is_string($match['content']));
assert(is_string($match['end']));

$children = $match['content'];


if (!@json_decode($children, true)) {
return false;
}

$subName = null;
if (in_array($mainNode, ['config', 'extra', 'scripts']) && false !== strpos($name, '.')) {
[$name, $subName] = explode('.', $name, 2);
}


if (!isset($decoded[$mainNode][$name]) || ($subName && !isset($decoded[$mainNode][$name][$subName]))) {
return true;
}


$keyRegex = str_replace('/', '\\\\?/', preg_quote($name));
if (Preg::isMatch('{"'.$keyRegex.'"\s*:}i', $children)) {

if (Preg::isMatchAll('{'.self::DEFINES.'"'.$keyRegex.'"\s*:\s*(?:(?&json))}x', $children, $matches)) {
$bestMatch = '';
foreach ($matches[0] as $match) {
assert(is_string($match));
if (strlen($bestMatch) < strlen($match)) {
$bestMatch = $match;
}
}
$childrenClean = Preg::replace('{,\s*'.preg_quote($bestMatch).'}i', '', $children, -1, $count);
if (1 !== $count) {
$childrenClean = Preg::replace('{'.preg_quote($bestMatch).'\s*,?\s*}i', '', $childrenClean, -1, $count);
if (1 !== $count) {
return false;
}
}
}
} else {
$childrenClean = $children;
}

if (!isset($childrenClean)) {
throw new \InvalidArgumentException("JsonManipulator: \$childrenClean is not defined. Please report at https://github.com/composer/composer/issues/new.");
}


unset($match);
Preg::match('#^{ \s*? (?P<content>\S+.*?)? (?P<trailingspace>\s*) }$#sx', $childrenClean, $match);
if (empty($match['content'])) {
$newline = $this->newline;
$indent = $this->indent;

$this->contents = Preg::replaceCallback($nodeRegex, static function ($matches) use ($indent, $newline): string {
return $matches['start'] . '{' . $newline . $indent . '}' . $matches['end'];
}, $this->contents);


if ($subName !== null) {
$curVal = json_decode($children, true);
unset($curVal[$name][$subName]);
$this->addSubNode($mainNode, $name, $curVal[$name]);
}

return true;
}

$this->contents = Preg::replaceCallback($nodeRegex, function ($matches) use ($name, $subName, $childrenClean): string {
assert(is_string($matches['content']));
if ($subName !== null) {
$curVal = json_decode($matches['content'], true);
unset($curVal[$name][$subName]);
$childrenClean = $this->format($curVal);
}

return $matches['start'] . $childrenClean . $matches['end'];
}, $this->contents);

return true;
}




public function addMainKey(string $key, $content): bool
{
$decoded = JsonFile::parseJson($this->contents);
$content = $this->format($content);


$regex = '{'.self::DEFINES.'^(?P<start>\s*\{\s*(?:(?&string)\s*:\s*(?&json)\s*,\s*)*?)'.
'(?P<key>'.preg_quote(JsonFile::encode($key)).'\s*:\s*(?&json))(?P<end>.*)}sx';
if (isset($decoded[$key]) && Preg::isMatch($regex, $this->contents, $matches)) {

if (!@json_decode('{'.$matches['key'].'}')) {
return false;
}

$this->contents = $matches['start'] . JsonFile::encode($key).': '.$content . $matches['end'];

return true;
}


if (Preg::isMatch('#[^{\s](\s*)\}$#', $this->contents, $match)) {
$this->contents = Preg::replace(
'#'.$match[1].'\}$#',
addcslashes(',' . $this->newline . $this->indent . JsonFile::encode($key). ': '. $content . $this->newline . '}', '\\$'),
$this->contents
);

return true;
}


$this->contents = Preg::replace(
'#\}$#',
addcslashes($this->indent . JsonFile::encode($key). ': '.$content . $this->newline . '}', '\\$'),
$this->contents
);

return true;
}

public function removeMainKey(string $key): bool
{
$decoded = JsonFile::parseJson($this->contents);

if (!array_key_exists($key, $decoded)) {
return true;
}


$regex = '{'.self::DEFINES.'^(?P<start>\s*\{\s*(?:(?&string)\s*:\s*(?&json)\s*,\s*)*?)'.
'(?P<removal>'.preg_quote(JsonFile::encode($key)).'\s*:\s*(?&json))\s*,?\s*(?P<end>.*)}sx';
if (Preg::isMatch($regex, $this->contents, $matches)) {
assert(is_string($matches['start']));
assert(is_string($matches['removal']));
assert(is_string($matches['end']));


if (!@json_decode('{'.$matches['removal'].'}')) {
return false;
}


if (Preg::isMatchStrictGroups('#,\s*$#', $matches['start']) && Preg::isMatch('#^\}$#', $matches['end'])) {
$matches['start'] = rtrim(Preg::replace('#,(\s*)$#', '$1', $matches['start']), $this->indent);
}

$this->contents = $matches['start'] . $matches['end'];
if (Preg::isMatch('#^\{\s*\}\s*$#', $this->contents)) {
$this->contents = "{\n}";
}

return true;
}

return false;
}

public function removeMainKeyIfEmpty(string $key): bool
{
$decoded = JsonFile::parseJson($this->contents);

if (!array_key_exists($key, $decoded)) {
return true;
}

if (is_array($decoded[$key]) && count($decoded[$key]) === 0) {
return $this->removeMainKey($key);
}

return true;
}




public function format($data, int $depth = 0): string
{
if (is_array($data)) {
reset($data);

if (is_numeric(key($data))) {
foreach ($data as $key => $val) {
$data[$key] = $this->format($val, $depth + 1);
}

return '['.implode(', ', $data).']';
}

$out = '{' . $this->newline;
$elems = [];
foreach ($data as $key => $val) {
$elems[] = str_repeat($this->indent, $depth + 2) . JsonFile::encode($key). ': '.$this->format($val, $depth + 1);
}

return $out . implode(','.$this->newline, $elems) . $this->newline . str_repeat($this->indent, $depth + 1) . '}';
}

return JsonFile::encode($data);
}

protected function detectIndenting(): void
{
if (Preg::isMatchStrictGroups('{^([ \t]+)"}m', $this->contents, $match)) {
$this->indent = $match[1];
} else {
$this->indent = '    ';
}
}
}
