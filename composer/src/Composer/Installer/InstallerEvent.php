<?php declare(strict_types=1);











namespace Composer\Installer;

use Composer\Composer;
use Composer\DependencyResolver\Transaction;
use Composer\EventDispatcher\Event;
use Composer\IO\IOInterface;

class InstallerEvent extends Event
{



private $composer;




private $io;




private $devMode;




private $executeOperations;




private $transaction;




public function __construct(string $eventName, Composer $composer, IOInterface $io, bool $devMode, bool $executeOperations, Transaction $transaction)
{
parent::__construct($eventName);

$this->composer = $composer;
$this->io = $io;
$this->devMode = $devMode;
$this->executeOperations = $executeOperations;
$this->transaction = $transaction;
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

public function isExecutingOperations(): bool
{
return $this->executeOperations;
}

public function getTransaction(): ?Transaction
{
return $this->transaction;
}
}
