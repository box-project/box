<?php declare(strict_types=1);











namespace Composer\Repository;

use Composer\Installer\InstallationManager;






class WritableArrayRepository extends ArrayRepository implements WritableRepositoryInterface
{
use CanonicalPackagesTrait;




protected $devPackageNames = [];


private $devMode = null;




public function getDevMode()
{
return $this->devMode;
}




public function setDevPackageNames(array $devPackageNames)
{
$this->devPackageNames = $devPackageNames;
}




public function getDevPackageNames()
{
return $this->devPackageNames;
}




public function write(bool $devMode, InstallationManager $installationManager)
{
$this->devMode = $devMode;
}




public function reload()
{
$this->devMode = null;
}
}
