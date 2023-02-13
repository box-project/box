<?php declare(strict_types=1);











namespace Composer\Script;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\EventDispatcher\Event as BaseEvent;







class Event extends BaseEvent
{



private $composer;




private $io;




private $devMode;




private $originatingEvent;











public function __construct(string $name, Composer $composer, IOInterface $io, bool $devMode = false, array $args = [], array $flags = [])
{
parent::__construct($name, $args, $flags);
$this->composer = $composer;
$this->io = $io;
$this->devMode = $devMode;
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






public function getOriginatingEvent(): ?BaseEvent
{
return $this->originatingEvent;
}






public function setOriginatingEvent(BaseEvent $event): self
{
$this->originatingEvent = $this->calculateOriginatingEvent($event);

return $this;
}




private function calculateOriginatingEvent(BaseEvent $event): BaseEvent
{
if ($event instanceof Event && $event->getOriginatingEvent()) {
return $this->calculateOriginatingEvent($event->getOriginatingEvent());
}

return $event;
}
}
