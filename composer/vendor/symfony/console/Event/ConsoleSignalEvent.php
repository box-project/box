<?php










namespace Symfony\Component\Console\Event;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;




final class ConsoleSignalEvent extends ConsoleEvent
{
private $handlingSignal;

public function __construct(Command $command, InputInterface $input, OutputInterface $output, int $handlingSignal)
{
parent::__construct($command, $input, $output);
$this->handlingSignal = $handlingSignal;
}

public function getHandlingSignal(): int
{
return $this->handlingSignal;
}
}
