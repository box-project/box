<?php declare(strict_types=1);











namespace Composer\Installer;

use React\Promise\PromiseInterface;
use Composer\Package\PackageInterface;
use Composer\Downloader\DownloadManager;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;







class ProjectInstaller implements InstallerInterface
{

private $installPath;

private $downloadManager;

private $filesystem;

public function __construct(string $installPath, DownloadManager $dm, Filesystem $fs)
{
$this->installPath = rtrim(strtr($installPath, '\\', '/'), '/').'/';
$this->downloadManager = $dm;
$this->filesystem = $fs;
}




public function supports(string $packageType): bool
{
return true;
}




public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package): bool
{
return false;
}




public function download(PackageInterface $package, ?PackageInterface $prevPackage = null): ?PromiseInterface
{
$installPath = $this->installPath;
if (file_exists($installPath) && !$this->filesystem->isDirEmpty($installPath)) {
throw new \InvalidArgumentException("Project directory $installPath is not empty.");
}
if (!is_dir($installPath)) {
mkdir($installPath, 0777, true);
}

return $this->downloadManager->download($package, $installPath, $prevPackage);
}




public function prepare($type, PackageInterface $package, ?PackageInterface $prevPackage = null): ?PromiseInterface
{
return $this->downloadManager->prepare($type, $package, $this->installPath, $prevPackage);
}




public function cleanup($type, PackageInterface $package, ?PackageInterface $prevPackage = null): ?PromiseInterface
{
return $this->downloadManager->cleanup($type, $package, $this->installPath, $prevPackage);
}




public function install(InstalledRepositoryInterface $repo, PackageInterface $package): ?PromiseInterface
{
return $this->downloadManager->install($package, $this->installPath);
}




public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target): ?PromiseInterface
{
throw new \InvalidArgumentException("not supported");
}




public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package): ?PromiseInterface
{
throw new \InvalidArgumentException("not supported");
}






public function getInstallPath(PackageInterface $package): string
{
return $this->installPath;
}
}
