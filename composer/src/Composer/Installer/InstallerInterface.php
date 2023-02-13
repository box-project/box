<?php declare(strict_types=1);











namespace Composer\Installer;

use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use InvalidArgumentException;
use React\Promise\PromiseInterface;







interface InstallerInterface
{





public function supports(string $packageType);









public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package);








public function download(PackageInterface $package, ?PackageInterface $prevPackage = null);














public function prepare(string $type, PackageInterface $package, ?PackageInterface $prevPackage = null);








public function install(InstalledRepositoryInterface $repo, PackageInterface $package);










public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target);








public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package);













public function cleanup(string $type, PackageInterface $package, ?PackageInterface $prevPackage = null);






public function getInstallPath(PackageInterface $package);
}
