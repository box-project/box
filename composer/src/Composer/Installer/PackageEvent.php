<?php declare(strict_types=1);











namespace Composer\Installer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\Repository\RepositoryInterface;
use Composer\EventDispatcher\Event;






class PackageEvent extends Event
{



private $composer;




private $io;




private $devMode;




private $localRepo;




private $operations;




private $operation;






public function __construct(string $eventName, Composer $composer, IOInterface $io, bool $devMode, RepositoryInterface $localRepo, array $operations, OperationInterface $operation)
{
parent::__construct($eventName);

$this->composer = $composer;
$this->io = $io;
$this->devMode = $devMode;
$this->localRepo = $localRepo;
$this->operations = $operations;
$this->operation = $operation;
}

public function getComposer(): Composer
{
return $this->composer;
}

public function getIO(): IOInterface
{
return $this->io;
}

public function isDevMode(): bool
{
return $this->devMode;
}

public function getLocalRepo(): RepositoryInterface
{
return $this->localRepo;
}




public function getOperations(): array
{
return $this->operations;
}




public function getOperation(): OperationInterface
{
return $this->operation;
}
}
