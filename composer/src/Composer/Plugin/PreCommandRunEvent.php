<?php declare(strict_types=1);











namespace Composer\Plugin;

use Composer\EventDispatcher\Event;
use Symfony\Component\Console\Input\InputInterface;






class PreCommandRunEvent extends Event
{



private $input;




private $command;







public function __construct(string $name, InputInterface $input, string $command)
{
parent::__construct($name);
$this->input = $input;
$this->command = $command;
}




public function getInput(): InputInterface
{
return $this->input;
}




public function getCommand(): string
{
return $this->command;
}
}
