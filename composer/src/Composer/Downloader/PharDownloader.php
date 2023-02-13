<?php declare(strict_types=1);











namespace Composer\Downloader;

use React\Promise\PromiseInterface;
use Composer\Package\PackageInterface;






class PharDownloader extends ArchiveDownloader
{



protected function extract(PackageInterface $package, string $file, string $path): PromiseInterface
{

$archive = new \Phar($file);
$archive->extractTo($path, null, true);






return \React\Promise\resolve(null);
}
}
