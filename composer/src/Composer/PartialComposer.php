<?php declare(strict_types=1);











namespace Composer;

use Composer\Package\RootPackageInterface;
use Composer\Util\Loop;
use Composer\Repository\RepositoryManager;
use Composer\Installer\InstallationManager;
use Composer\EventDispatcher\EventDispatcher;




class PartialComposer
{



private $package;




private $loop;




private $repositoryManager;




private $installationManager;




private $config;




private $eventDispatcher;

public function setPackage(RootPackageInterface $package): void
{
$this->package = $package;
}

public function getPackage(): RootPackageInterface
{
return $this->package;
}

public function setConfig(Config $config): void
{
$this->config = $config;
}

public function getConfig(): Config
{
return $this->config;
}

public function setLoop(Loop $loop): void
{
$this->loop = $loop;
}

public function getLoop(): Loop
{
return $this->loop;
}

public function setRepositoryManager(RepositoryManager $manager): void
{
$this->repositoryManager = $manager;
}

public function getRepositoryManager(): RepositoryManager
{
return $this->repositoryManager;
}

public function setInstallationManager(InstallationManager $manager): void
{
$this->installationManager = $manager;
}

public function getInstallationManager(): InstallationManager
{
return $this->installationManager;
}

public function setEventDispatcher(EventDispatcher $eventDispatcher): void
{
$this->eventDispatcher = $eventDispatcher;
}

public function getEventDispatcher(): EventDispatcher
{
return $this->eventDispatcher;
}
}
