<?php declare(strict_types=1);











namespace Composer\Package\Archiver;

use Composer\Pcre\Preg;
use Composer\Util\Filesystem;
use FilesystemIterator;
use FilterIterator;
use Iterator;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;










class ArchivableFilesFinder extends FilterIterator
{



protected $finder;








public function __construct(string $sources, array $excludes, bool $ignoreFilters = false)
{
$fs = new Filesystem();

$sources = $fs->normalizePath(realpath($sources));

if ($ignoreFilters) {
$filters = [];
} else {
$filters = [
new GitExcludeFilter($sources),
new ComposerExcludeFilter($sources, $excludes),
];
}

$this->finder = new Finder();

$filter = static function (\SplFileInfo $file) use ($sources, $filters, $fs): bool {
if ($file->isLink() && ($file->getRealPath() === false || strpos($file->getRealPath(), $sources) !== 0)) {
return false;
}

$relativePath = Preg::replace(
'#^'.preg_quote($sources, '#').'#',
'',
$fs->normalizePath($file->getRealPath())
);

$exclude = false;
foreach ($filters as $filter) {
$exclude = $filter->filter($relativePath, $exclude);
}

return !$exclude;
};

if (method_exists($filter, 'bindTo')) {
$filter = $filter->bindTo(null);
}

$this->finder
->in($sources)
->filter($filter)
->ignoreVCS(true)
->ignoreDotFiles(false)
->sortByName();

parent::__construct($this->finder->getIterator());
}

public function accept(): bool
{

$current = $this->getInnerIterator()->current();

if (!$current->isDir()) {
return true;
}

$iterator = new FilesystemIterator((string) $current, FilesystemIterator::SKIP_DOTS);

return !$iterator->valid();
}
}
