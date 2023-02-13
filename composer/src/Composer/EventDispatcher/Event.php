<?php declare(strict_types=1);











namespace Composer\EventDispatcher;






class Event
{



protected $name;




protected $args;




protected $flags;




private $propagationStopped = false;








public function __construct(string $name, array $args = [], array $flags = [])
{
$this->name = $name;
$this->args = $args;
$this->flags = $flags;
}






public function getName(): string
{
return $this->name;
}






public function getArguments(): array
{
return $this->args;
}






public function getFlags(): array
{
return $this->flags;
}






public function isPropagationStopped(): bool
{
return $this->propagationStopped;
}




public function stopPropagation(): void
{
$this->propagationStopped = true;
}
}
