<?php declare(strict_types=1);











namespace Composer\Downloader;

use Composer\Package\PackageInterface;
use React\Promise\PromiseInterface;






class TarDownloader extends ArchiveDownloader
{



protected function extract(PackageInterface $package, string $file, string $path): PromiseInterface
{

$archive = new \PharData($file);
$archive->extractTo($path, null, true);

return \React\Promise\resolve(null);
}
}
