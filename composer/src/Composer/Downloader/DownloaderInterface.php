<?php declare(strict_types=1);











namespace Composer\Downloader;

use Composer\Package\PackageInterface;
use React\Promise\PromiseInterface;







interface DownloaderInterface
{





public function getInstallationSource(): string;






public function download(PackageInterface $package, string $path, ?PackageInterface $prevPackage = null): PromiseInterface;














public function prepare(string $type, PackageInterface $package, string $path, ?PackageInterface $prevPackage = null): PromiseInterface;







public function install(PackageInterface $package, string $path): PromiseInterface;








public function update(PackageInterface $initial, PackageInterface $target, string $path): PromiseInterface;







public function remove(PackageInterface $package, string $path): PromiseInterface;













public function cleanup(string $type, PackageInterface $package, string $path, ?PackageInterface $prevPackage = null): PromiseInterface;
}
