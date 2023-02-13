<?php declare(strict_types=1);











namespace Composer\Downloader;

use Composer\Package\PackageInterface;






interface ChangeReportInterface
{







public function getLocalChanges(PackageInterface $package, string $path): ?string;
}
