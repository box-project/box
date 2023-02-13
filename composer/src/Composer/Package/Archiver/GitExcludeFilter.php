<?php declare(strict_types=1);











namespace Composer\Package\Archiver;

use Composer\Pcre\Preg;








class GitExcludeFilter extends BaseExcludeFilter
{



public function __construct(string $sourcePath)
{
parent::__construct($sourcePath);

if (file_exists($sourcePath.'/.gitattributes')) {
$this->excludePatterns = array_merge(
$this->excludePatterns,
$this->parseLines(
file($sourcePath.'/.gitattributes'),
[$this, 'parseGitAttributesLine']
)
);
}
}








public function parseGitAttributesLine(string $line): ?array
{
$parts = Preg::split('#\s+#', $line);

if (count($parts) === 2 && $parts[1] === 'export-ignore') {
return $this->generatePattern($parts[0]);
}

if (count($parts) === 2 && $parts[1] === '-export-ignore') {
return $this->generatePattern('!'.$parts[0]);
}

return null;
}
}
