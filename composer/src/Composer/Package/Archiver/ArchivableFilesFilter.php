<?php declare(strict_types=1);











namespace Composer\Package\Archiver;

use FilterIterator;
use Iterator;
use PharData;
use SplFileInfo;




class ArchivableFilesFilter extends FilterIterator
{

private $dirs = [];




public function accept(): bool
{
$file = $this->getInnerIterator()->current();
if ($file->isDir()) {
$this->dirs[] = (string) $file;

return false;
}

return true;
}

public function addEmptyDir(PharData $phar, string $sources): void
{
foreach ($this->dirs as $filepath) {
$localname = str_replace($sources . "/", '', $filepath);
$phar->addEmptyDir($localname);
}
}
}
