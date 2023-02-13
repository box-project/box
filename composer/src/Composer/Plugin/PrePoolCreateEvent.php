<?php declare(strict_types=1);











namespace Composer\Plugin;

use Composer\EventDispatcher\Event;
use Composer\Repository\RepositoryInterface;
use Composer\DependencyResolver\Request;
use Composer\Package\BasePackage;






class PrePoolCreateEvent extends Event
{



private $repositories;



private $request;




private $acceptableStabilities;




private $stabilityFlags;




private $rootAliases;




private $rootReferences;



private $packages;



private $unacceptableFixedPackages;
















public function __construct(string $name, array $repositories, Request $request, array $acceptableStabilities, array $stabilityFlags, array $rootAliases, array $rootReferences, array $packages, array $unacceptableFixedPackages)
{
parent::__construct($name);

$this->repositories = $repositories;
$this->request = $request;
$this->acceptableStabilities = $acceptableStabilities;
$this->stabilityFlags = $stabilityFlags;
$this->rootAliases = $rootAliases;
$this->rootReferences = $rootReferences;
$this->packages = $packages;
$this->unacceptableFixedPackages = $unacceptableFixedPackages;
}




public function getRepositories(): array
{
return $this->repositories;
}

public function getRequest(): Request
{
return $this->request;
}





public function getAcceptableStabilities(): array
{
return $this->acceptableStabilities;
}





public function getStabilityFlags(): array
{
return $this->stabilityFlags;
}





public function getRootAliases(): array
{
return $this->rootAliases;
}





public function getRootReferences(): array
{
return $this->rootReferences;
}




public function getPackages(): array
{
return $this->packages;
}




public function getUnacceptableFixedPackages(): array
{
return $this->unacceptableFixedPackages;
}




public function setPackages(array $packages): void
{
$this->packages = $packages;
}




public function setUnacceptableFixedPackages(array $packages): void
{
$this->unacceptableFixedPackages = $packages;
}
}
