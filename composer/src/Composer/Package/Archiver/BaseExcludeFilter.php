<?php declare(strict_types=1);











namespace Composer\Package\Archiver;

use Composer\Pcre\Preg;
use Symfony\Component\Finder;




abstract class BaseExcludeFilter
{



protected $sourcePath;




protected $excludePatterns;




public function __construct(string $sourcePath)
{
$this->sourcePath = $sourcePath;
$this->excludePatterns = [];
}











public function filter(string $relativePath, bool $exclude): bool
{
foreach ($this->excludePatterns as $patternData) {
[$pattern, $negate, $stripLeadingSlash] = $patternData;

if ($stripLeadingSlash) {
$path = substr($relativePath, 1);
} else {
$path = $relativePath;
}

try {
if (Preg::isMatch($pattern, $path)) {
$exclude = !$negate;
}
} catch (\RuntimeException $e) {

}
}

return $exclude;
}









protected function parseLines(array $lines, callable $lineParser): array
{
return array_filter(
array_map(
static function ($line) use ($lineParser) {
$line = trim($line);

if (!$line || 0 === strpos($line, '#')) {
return null;
}

return $lineParser($line);
},
$lines
),
static function ($pattern): bool {
return $pattern !== null;
}
);
}








protected function generatePatterns(array $rules): array
{
$patterns = [];
foreach ($rules as $rule) {
$patterns[] = $this->generatePattern($rule);
}

return $patterns;
}








protected function generatePattern(string $rule): array
{
$negate = false;
$pattern = '';

if ($rule !== '' && $rule[0] === '!') {
$negate = true;
$rule = ltrim($rule, '!');
}

$firstSlashPosition = strpos($rule, '/');
if (0 === $firstSlashPosition) {
$pattern = '^/';
} elseif (false === $firstSlashPosition || strlen($rule) - 1 === $firstSlashPosition) {
$pattern = '/';
}

$rule = trim($rule, '/');


$rule = substr(Finder\Glob::toRegex($rule), 2, -2);

return ['{'.$pattern.$rule.'(?=$|/)}', $negate, false];
}
}
