<?php










namespace Seld\Signal;

use Psr\Log\LoggerInterface;
use Closure;
use WeakReference;




final class SignalHandler
{







public const SIGHUP = 'SIGHUP';








public const SIGINT = 'SIGINT';





public const SIGQUIT = 'SIGQUIT';





public const SIGILL = 'SIGILL';






public const SIGTRAP = 'SIGTRAP';






public const SIGABRT = 'SIGABRT';

public const SIGIOT = 'SIGIOT';





public const SIGBUS = 'SIGBUS';

public const SIGFPE = 'SIGFPE';






public const SIGKILL = 'SIGKILL';




public const SIGUSR1 = 'SIGUSR1';




public const SIGUSR2 = 'SIGUSR2';





public const SIGSEGV = 'SIGSEGV';





public const SIGPIPE = 'SIGPIPE';







public const SIGALRM = 'SIGALRM';






public const SIGTERM = 'SIGTERM';

public const SIGSTKFLT = 'SIGSTKFLT';
public const SIGCLD = 'SIGCLD';






public const SIGCHLD = 'SIGCHLD';





public const SIGCONT = 'SIGCONT';




public const SIGSTOP = 'SIGSTOP';






public const SIGTSTP = 'SIGTSTP';





public const SIGTTIN = 'SIGTTIN';





public const SIGTTOU = 'SIGTTOU';




public const SIGURG = 'SIGURG';







public const SIGXCPU = 'SIGXCPU';




public const SIGXFSZ = 'SIGXFSZ';





public const SIGVTALRM = 'SIGVTALRM';






public const SIGPROF = 'SIGPROF';




public const SIGWINCH = 'SIGWINCH';






public const SIGPOLL = 'SIGPOLL';

public const SIGIO = 'SIGIO';




public const SIGPWR = 'SIGPWR';





public const SIGSYS = 'SIGSYS';

public const SIGBABY = 'SIGBABY';




public const SIGBREAK = 'SIGBREAK';

private const ALL_SIGNALS = [
self::SIGHUP, self::SIGINT, self::SIGQUIT, self::SIGILL, self::SIGTRAP, self::SIGABRT, self::SIGIOT, self::SIGBUS,
self::SIGFPE, self::SIGKILL, self::SIGUSR1, self::SIGUSR2, self::SIGSEGV, self::SIGPIPE, self::SIGALRM, self::SIGTERM,
self::SIGSTKFLT, self::SIGCLD, self::SIGCHLD, self::SIGCONT, self::SIGSTOP, self::SIGTSTP, self::SIGTTIN, self::SIGTTOU,
self::SIGURG, self::SIGXCPU, self::SIGXFSZ, self::SIGVTALRM, self::SIGPROF, self::SIGWINCH, self::SIGPOLL, self::SIGIO,
self::SIGPWR, self::SIGSYS, self::SIGBABY, self::SIGBREAK
];




private $triggered = null;





private $signals;





private $loggerOrCallback;




private static $handlers = [];


private static $windowsHandler = null;





private function __construct(array $signals, $loggerOrCallback)
{
if (!is_callable($loggerOrCallback) && !$loggerOrCallback instanceof LoggerInterface && $loggerOrCallback !== null) {
throw new \InvalidArgumentException('$loggerOrCallback must be a '.LoggerInterface::class.' instance, a callable, or null, '.(is_object($loggerOrCallback) ? get_class($loggerOrCallback) : gettype($loggerOrCallback)).' received.');
}

$this->signals = $signals;
$this->loggerOrCallback = $loggerOrCallback;
}




private function trigger(string $signalName): void
{
$this->triggered = $signalName;

if ($this->loggerOrCallback instanceof LoggerInterface) {
$this->loggerOrCallback->info('Received '.$signalName);
} elseif ($this->loggerOrCallback !== null) {
($this->loggerOrCallback)($signalName, $this);
}
}






public function isTriggered(): bool
{
return $this->triggered !== null;
}































public function exitWithLastSignal(): void
{
$signal = $this->triggered ?? 'SIGINT';
$signal = defined($signal) ? constant($signal) : 2;

if (function_exists('posix_kill') && function_exists('posix_getpid')) {
pcntl_signal($signal, SIG_DFL);
posix_kill(posix_getpid(), $signal);
}



exit(128 + $signal);
}




public function reset(): void
{
$this->triggered = null;
}

public function __destruct()
{
$this->unregister();
}









public static function create(?array $signals = null, $loggerOrCallback = null): self
{
if ($signals === null) {
$signals = [self::SIGINT, self::SIGTERM];
}
$signals = array_map(function ($signal) {
if (is_int($signal)) {
return self::getSignalName($signal);
} elseif (!in_array($signal, self::ALL_SIGNALS, true)) {
throw new \InvalidArgumentException('$signals must be an array of SIG* constants or self::SIG* constants, got '.var_export($signal, true));
}
return $signal;
}, (array) $signals);

$handler = new self($signals, $loggerOrCallback);

if (PHP_VERSION_ID >= 80000) {
array_unshift(self::$handlers, WeakReference::create($handler));
} else {
array_unshift(self::$handlers, $handler);
}

if (function_exists('sapi_windows_set_ctrl_handler') && PHP_SAPI === 'cli' && (in_array(self::SIGINT, $signals, true) || in_array(self::SIGBREAK, $signals, true))) {
if (null === self::$windowsHandler) {
self::$windowsHandler = Closure::fromCallable([self::class, 'handleWindowsSignal']);
sapi_windows_set_ctrl_handler(self::$windowsHandler);
}
}

if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
pcntl_async_signals(true);

self::registerPcntlHandler($signals);
}

return $handler;
}









public function unregister(): void
{
$signals = $this->signals;

$index = false;
foreach (self::$handlers as $key => $handler) {
if (($handler instanceof WeakReference && $handler->get() === $this) || $handler === $this) {
$index = $key;
break;
}
}
if ($index === false) {

return;
}

unset(self::$handlers[$index]);

if (self::$windowsHandler !== null && (in_array(self::SIGINT, $signals, true) || in_array(self::SIGBREAK, $signals, true))) {
if (self::getHandlerFor(self::SIGINT) === null && self::getHandlerFor(self::SIGBREAK) === null) {
sapi_windows_set_ctrl_handler(self::$windowsHandler, false);
self::$windowsHandler = null;
}
}

if (function_exists('pcntl_signal')) {
foreach ($signals as $signal) {

if (!defined($signal)) {
continue;
}


if (self::getHandlerFor($signal) !== null) {
continue;
}

pcntl_signal(constant($signal), SIG_DFL);
}
}
}









public static function unregisterAll(): void
{
if (self::$windowsHandler !== null) {
sapi_windows_set_ctrl_handler(self::$windowsHandler, false);
self::$windowsHandler = null;
}

foreach (self::$handlers as $key => $handler) {
if ($handler instanceof WeakReference) {
$handler = $handler->get();
if ($handler === null) {
unset(self::$handlers[$key]);
continue;
}
}
$handler->unregister();
}
}




private static function registerPcntlHandler(array $signals): void
{
static $callable;
if ($callable === null) {
$callable = Closure::fromCallable([self::class, 'handlePcntlSignal']);
}
foreach ($signals as $signal) {

if (!defined($signal)) {
continue;
}

pcntl_signal(constant($signal), $callable);
}
}

private static function handleWindowsSignal(int $event): void
{
if (PHP_WINDOWS_EVENT_CTRL_C === $event) {
self::callHandlerFor(self::SIGINT);
} elseif (PHP_WINDOWS_EVENT_CTRL_BREAK === $event) {
self::callHandlerFor(self::SIGBREAK);
}
}

private static function handlePcntlSignal(int $signal): void
{
self::callHandlerFor(self::getSignalName($signal));
}






private static function callHandlerFor(string $signal): void
{
$handler = self::getHandlerFor($signal);
if ($handler !== null) {
$handler->trigger($signal);
}
}







private static function getHandlerFor(string $signal): ?self
{
foreach (self::$handlers as $key => $handler) {
if ($handler instanceof WeakReference) {
$handler = $handler->get();
if ($handler === null) {
unset(self::$handlers[$key]);
continue;
}
}
if (in_array($signal, $handler->signals, true)) {
return $handler;
}
}

return null;
}




private static function getSignalName(int $signo): string
{
static $signals = null;
if ($signals === null) {
$signals = [];
foreach (self::ALL_SIGNALS as $value) {
if (defined($value)) {
$signals[constant($value)] = $value;
}
}
}

if (isset($signals[$signo])) {
return $signals[$signo];
}

throw new \InvalidArgumentException('Unknown signal #'.$signo);
}
}
