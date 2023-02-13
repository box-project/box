<?php declare(strict_types=1);











namespace Composer\Plugin;

use Composer\EventDispatcher\Event;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;






class CommandEvent extends Event
{



private $commandName;




private $input;




private $output;









public function __construct(string $name, string $commandName, InputInterface $input, OutputInterface $output, array $args = [], array $flags = [])
{
parent::__construct($name, $args, $flags);
$this->commandName = $commandName;
$this->input = $input;
$this->output = $output;
}




public function getInput(): InputInterface
{
return $this->input;
}




public function getOutput(): OutputInterface
{
return $this->output;
}




public function getCommandName(): string
{
return $this->commandName;
}
}
