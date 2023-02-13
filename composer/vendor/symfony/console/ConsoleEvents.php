<?php










namespace Symfony\Component\Console;

use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;






final class ConsoleEvents
{







public const COMMAND = 'console.command';







public const SIGNAL = 'console.signal';







public const TERMINATE = 'console.terminate';









public const ERROR = 'console.error';






public const ALIASES = [
ConsoleCommandEvent::class => self::COMMAND,
ConsoleErrorEvent::class => self::ERROR,
ConsoleSignalEvent::class => self::SIGNAL,
ConsoleTerminateEvent::class => self::TERMINATE,
];
}
