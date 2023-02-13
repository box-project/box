<?php declare(strict_types=1);











namespace Composer\Util;

use Composer\IO\IOInterface;
use Composer\Pcre\Preg;
use Seld\Signal\SignalHandler;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\RuntimeException;
use React\Promise\Promise;
use React\Promise\PromiseInterface;





class ProcessExecutor
{
private const STATUS_QUEUED = 1;
private const STATUS_STARTED = 2;
private const STATUS_COMPLETED = 3;
private const STATUS_FAILED = 4;
private const STATUS_ABORTED = 5;


protected static $timeout = 300;


protected $captureOutput = false;

protected $errorOutput = '';

protected $io;




private $jobs = [];

private $runningJobs = 0;

private $maxJobs = 10;

private $idGen = 0;

private $allowAsync = false;

public function __construct(?IOInterface $io = null)
{
$this->io = $io;
}










public function execute($command, &$output = null, ?string $cwd = null): int
{
if (func_num_args() > 1) {
return $this->doExecute($command, $cwd, false, $output);
}

return $this->doExecute($command, $cwd, false);
}








public function executeTty($command, ?string $cwd = null): int
{
if (Platform::isTty()) {
return $this->doExecute($command, $cwd, true);
}

return $this->doExecute($command, $cwd, false);
}





private function doExecute($command, ?string $cwd, bool $tty, &$output = null): int
{
$this->outputCommandRun($command, $cwd, false);

$this->captureOutput = func_num_args() > 3;
$this->errorOutput = '';

if (is_string($command)) {
$process = Process::fromShellCommandline($command, $cwd, null, null, static::getTimeout());
} else {
$process = new Process($command, $cwd, null, null, static::getTimeout());
}

if (!Platform::isWindows() && $tty) {
try {
$process->setTty(true);
} catch (RuntimeException $e) {

}
}

$callback = is_callable($output) ? $output : function (string $type, string $buffer): void {
$this->outputHandler($type, $buffer);
};

$signalHandler = SignalHandler::create([SignalHandler::SIGINT, SignalHandler::SIGTERM, SignalHandler::SIGHUP], function (string $signal) {
if ($this->io !== null) {
$this->io->writeError('Received '.$signal.', aborting when child process is done', true, IOInterface::DEBUG);
}
});

try {
$process->run($callback);

if ($this->captureOutput && !is_callable($output)) {
$output = $process->getOutput();
}

$this->errorOutput = $process->getErrorOutput();
} catch (ProcessSignaledException $e) {
if ($signalHandler->isTriggered()) {

$signalHandler->exitWithLastSignal();
}
} finally {
$signalHandler->unregister();
}

return $process->getExitCode();
}







public function executeAsync($command, ?string $cwd = null): PromiseInterface
{
if (!$this->allowAsync) {
throw new \LogicException('You must use the ProcessExecutor instance which is part of a Composer\Loop instance to be able to run async processes');
}

$job = [
'id' => $this->idGen++,
'status' => self::STATUS_QUEUED,
'command' => $command,
'cwd' => $cwd,
];

$resolver = static function ($resolve, $reject) use (&$job): void {
$job['status'] = ProcessExecutor::STATUS_QUEUED;
$job['resolve'] = $resolve;
$job['reject'] = $reject;
};

$canceler = static function () use (&$job): void {
if ($job['status'] === ProcessExecutor::STATUS_QUEUED) {
$job['status'] = ProcessExecutor::STATUS_ABORTED;
}
if ($job['status'] !== ProcessExecutor::STATUS_STARTED) {
return;
}
$job['status'] = ProcessExecutor::STATUS_ABORTED;
try {
if (defined('SIGINT')) {
$job['process']->signal(SIGINT);
}
} catch (\Exception $e) {

}
$job['process']->stop(1);

throw new \RuntimeException('Aborted process');
};

$promise = new Promise($resolver, $canceler);
$promise = $promise->then(function () use (&$job) {
if ($job['process']->isSuccessful()) {
$job['status'] = ProcessExecutor::STATUS_COMPLETED;
} else {
$job['status'] = ProcessExecutor::STATUS_FAILED;
}

$this->markJobDone();

return $job['process'];
}, function ($e) use (&$job): void {
$job['status'] = ProcessExecutor::STATUS_FAILED;

$this->markJobDone();

throw $e;
});
$this->jobs[$job['id']] = &$job;

if ($this->runningJobs < $this->maxJobs) {
$this->startJob($job['id']);
}

return $promise;
}

protected function outputHandler(string $type, string $buffer): void
{
if ($this->captureOutput) {
return;
}

if (null === $this->io) {
echo $buffer;

return;
}

if (Process::ERR === $type) {
$this->io->writeErrorRaw($buffer, false);
} else {
$this->io->writeRaw($buffer, false);
}
}

private function startJob(int $id): void
{
$job = &$this->jobs[$id];
if ($job['status'] !== self::STATUS_QUEUED) {
return;
}


$job['status'] = self::STATUS_STARTED;
$this->runningJobs++;

$command = $job['command'];
$cwd = $job['cwd'];

$this->outputCommandRun($command, $cwd, true);

try {
if (is_string($command)) {
$process = Process::fromShellCommandline($command, $cwd, null, null, static::getTimeout());
} else {
$process = new Process($command, $cwd, null, null, static::getTimeout());
}
} catch (\Throwable $e) {
$job['reject']($e);

return;
}

$job['process'] = $process;

try {
$process->start();
} catch (\Throwable $e) {
$job['reject']($e);

return;
}
}

public function setMaxJobs(int $maxJobs): void
{
$this->maxJobs = $maxJobs;
}

public function resetMaxJobs(): void
{
$this->maxJobs = 10;
}




public function wait($index = null): void
{
while (true) {
if (0 === $this->countActiveJobs($index)) {
return;
}

usleep(1000);
}
}




public function enableAsync(): void
{
$this->allowAsync = true;
}







public function countActiveJobs($index = null): int
{

foreach ($this->jobs as $job) {
if ($job['status'] === self::STATUS_STARTED) {
if (!$job['process']->isRunning()) {
call_user_func($job['resolve'], $job['process']);
}

$job['process']->checkTimeout();
}

if ($this->runningJobs < $this->maxJobs) {
if ($job['status'] === self::STATUS_QUEUED) {
$this->startJob($job['id']);
}
}
}

if (null !== $index) {
return $this->jobs[$index]['status'] < self::STATUS_COMPLETED ? 1 : 0;
}

$active = 0;
foreach ($this->jobs as $job) {
if ($job['status'] < self::STATUS_COMPLETED) {
$active++;
} else {
unset($this->jobs[$job['id']]);
}
}

return $active;
}

private function markJobDone(): void
{
$this->runningJobs--;
}




public function splitLines(?string $output): array
{
$output = trim((string) $output);

return $output === '' ? [] : Preg::split('{\r?\n}', $output);
}




public function getErrorOutput(): string
{
return $this->errorOutput;
}




public static function getTimeout(): int
{
return static::$timeout;
}




public static function setTimeout(int $timeout): void
{
static::$timeout = $timeout;
}








public static function escape($argument): string
{
return self::escapeArgument($argument);
}




private function outputCommandRun($command, ?string $cwd, bool $async): void
{
if (null === $this->io || !$this->io->isDebug()) {
return;
}

$commandString = is_string($command) ? $command : implode(' ', array_map(self::class.'::escape', $command));
$safeCommand = Preg::replaceCallback('{://(?P<user>[^:/\s]+):(?P<password>[^@\s/]+)@}i', static function ($m): string {
assert(is_string($m['user']));


if (Preg::isMatch('{^([a-f0-9]{12,}|gh[a-z]_[a-zA-Z0-9_]+)$}', $m['user'])) {
return '://***:***@';
}
if (Preg::isMatch('{^[a-f0-9]{12,}$}', $m['user'])) {
return '://***:***@';
}

return '://'.$m['user'].':***@';
}, $commandString);
$safeCommand = Preg::replace("{--password (.*[^\\\\]\') }", '--password \'***\' ', $safeCommand);
$this->io->writeError('Executing'.($async ? ' async' : '').' command ('.($cwd ?: 'CWD').'): '.$safeCommand);
}














private static function escapeArgument($argument): string
{
if ('' === ($argument = (string) $argument)) {
return escapeshellarg($argument);
}

if (!Platform::isWindows()) {
return "'".str_replace("'", "'\\''", $argument)."'";
}


$argument = strtr($argument, "\n", ' ');


$quote = strpbrk($argument, " \t,") !== false;
$argument = Preg::replace('/(\\\\*)"/', '$1$1\\"', $argument, -1, $dquotes);
$meta = $dquotes > 0 || Preg::isMatch('/%[^%]+%|![^!]+!/', $argument);

if (!$meta && !$quote) {
$quote = strpbrk($argument, '^&|<>()') !== false;
}

if ($quote) {
$argument = '"'.Preg::replace('/(\\\\*)$/', '$1$1', $argument).'"';
}

if ($meta) {
$argument = Preg::replace('/(["^&|<>()%])/', '^$1', $argument);
$argument = Preg::replace('/(!)/', '^^$1', $argument);
}

return $argument;
}
}
