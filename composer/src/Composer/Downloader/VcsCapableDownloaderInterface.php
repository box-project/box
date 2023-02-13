<?php declare(strict_types=1);











namespace Composer\Downloader;

use Composer\Package\PackageInterface;






interface VcsCapableDownloaderInterface
{







public function getVcsReference(PackageInterface $package, string $path): ?string;
}
