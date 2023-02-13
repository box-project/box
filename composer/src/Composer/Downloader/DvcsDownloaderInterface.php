<?php declare(strict_types=1);











namespace Composer\Downloader;

use Composer\Package\PackageInterface;






interface DvcsDownloaderInterface
{







public function getUnpushedChanges(PackageInterface $package, string $path): ?string;
}
