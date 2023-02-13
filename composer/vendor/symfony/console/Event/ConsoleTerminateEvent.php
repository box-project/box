<?php










namespace Symfony\Component\Console\Event;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;






final class ConsoleTerminateEvent extends ConsoleEvent
{
private $exitCode;

public function __construct(Command $command, InputInterface $input, OutputInterface $output, int $exitCode)
{
parent::__construct($command, $input, $output);

$this->setExitCode($exitCode);
}

public function setExitCode(int $exitCode): void
{
$this->exitCode = $exitCode;
}

public function getExitCode(): int
{
return $this->exitCode;
}
}
