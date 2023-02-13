<?php declare(strict_types=1);











namespace Composer\Console;

use Composer\IO\NullIO;
use Composer\Util\Filesystem;
use Composer\Util\Platform;
use Composer\Util\Silencer;
use LogicException;
use RuntimeException;
use Seld\Signal\SignalHandler;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Seld\JsonLint\ParsingException;
use Composer\Command;
use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\IO\ConsoleIO;
use Composer\Json\JsonValidationException;
use Composer\Util\ErrorHandler;
use Composer\Util\HttpDownloader;
use Composer\EventDispatcher\ScriptExecutionException;
use Composer\Exception\NoSslException;
use Composer\XdebugHandler\XdebugHandler;
use Symfony\Component\Process\Exception\ProcessTimedOutException;








class Application extends BaseApplication
{



protected $composer;




protected $io;


private static $logo = '   ______
  / ____/___  ____ ___  ____  ____  ________  _____
 / /   / __ \/ __ `__ \/ __ \/ __ \/ ___/ _ \/ ___/
/ /___/ /_/ / / / / / / /_/ / /_/ (__  )  __/ /
\____/\____/_/ /_/ /_/ .___/\____/____/\___/_/
                    /_/
';


private $hasPluginCommands = false;

private $disablePluginsByDefault = false;

private $disableScriptsByDefault = false;




private $initialWorkingDirectory;


private $signalHandler;

public function __construct(string $name = 'Composer', string $version = '')
{
static $shutdownRegistered = false;
if ($version === '') {
$version = Composer::getVersion();
}
if (function_exists('ini_set') && extension_loaded('xdebug')) {
ini_set('xdebug.show_exception_trace', '0');
ini_set('xdebug.scream', '0');
}

if (function_exists('date_default_timezone_set') && function_exists('date_default_timezone_get')) {
date_default_timezone_set(Silencer::call('date_default_timezone_get'));
}

$this->io = new NullIO();

$this->signalHandler = SignalHandler::create([SignalHandler::SIGINT, SignalHandler::SIGTERM, SignalHandler::SIGHUP], function (string $signal, SignalHandler $handler) {
$this->io->writeError('Received '.$signal.', aborting', true, IOInterface::DEBUG);

$handler->exitWithLastSignal();
});

if (!$shutdownRegistered) {
$shutdownRegistered = true;

register_shutdown_function(static function (): void {
$lastError = error_get_last();

if ($lastError && $lastError['message'] &&
(strpos($lastError['message'], 'Allowed memory') !== false  ||
strpos($lastError['message'], 'exceeded memory') !== false )) {
echo "\n". 'Check https://getcomposer.org/doc/articles/troubleshooting.md#memory-limit-errors for more info on how to handle out of memory errors.';
}
});
}

$this->initialWorkingDirectory = getcwd();

parent::__construct($name, $version);
}

public function __destruct()
{
$this->signalHandler->unregister();
}

public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
{
if (null === $output) {
$output = Factory::createOutput();
}

return parent::run($input, $output);
}

public function doRun(InputInterface $input, OutputInterface $output): int
{
$this->disablePluginsByDefault = $input->hasParameterOption('--no-plugins');
$this->disableScriptsByDefault = $input->hasParameterOption('--no-scripts');

$stdin = defined('STDIN') ? STDIN : fopen('php://stdin', 'r');
if (Platform::getEnv('COMPOSER_TESTS_ARE_RUNNING') !== '1' && (Platform::getEnv('COMPOSER_NO_INTERACTION') || $stdin === false || !Platform::isTty($stdin))) {
$input->setInteractive(false);
}

$io = $this->io = new ConsoleIO($input, $output, new HelperSet([
new QuestionHelper(),
]));


ErrorHandler::register($io);

if ($input->hasParameterOption('--no-cache')) {
$io->writeError('Disabling cache usage', true, IOInterface::DEBUG);
Platform::putEnv('COMPOSER_CACHE_DIR', Platform::isWindows() ? 'nul' : '/dev/null');
}


$newWorkDir = $this->getNewWorkingDir($input);
if (null !== $newWorkDir) {
$oldWorkingDir = Platform::getCwd(true);
chdir($newWorkDir);
$this->initialWorkingDirectory = $newWorkDir;
$cwd = Platform::getCwd(true);
$io->writeError('Changed CWD to ' . ($cwd !== '' ? $cwd : $newWorkDir), true, IOInterface::DEBUG);
}


$commandName = '';
if ($name = $this->getCommandName($input)) {
try {
$commandName = $this->find($name)->getName();
} catch (CommandNotFoundException $e) {

$commandName = false;
} catch (\InvalidArgumentException $e) {
}
}


if ($io->isInteractive() && null === $newWorkDir && !in_array($commandName, ['', 'list', 'init', 'about', 'help', 'diagnose', 'self-update', 'global', 'create-project', 'outdated'], true) && !file_exists(Factory::getComposerFile()) && ($useParentDirIfNoJsonAvailable = $this->getUseParentDirConfigValue()) !== false) {
$dir = dirname(Platform::getCwd(true));
$home = realpath(Platform::getEnv('HOME') ?: Platform::getEnv('USERPROFILE') ?: '/');


while (dirname($dir) !== $dir && $dir !== $home) {
if (file_exists($dir.'/'.Factory::getComposerFile())) {
if ($useParentDirIfNoJsonAvailable === true || $io->askConfirmation('<info>No composer.json in current directory, do you want to use the one at '.$dir.'?</info> [<comment>Y,n</comment>]? ')) {
if ($useParentDirIfNoJsonAvailable === true) {
$io->writeError('<info>No composer.json in current directory, changing working directory to '.$dir.'</info>');
} else {
$io->writeError('<info>Always want to use the parent dir? Use "composer config --global use-parent-dir true" to change the default.</info>');
}
$oldWorkingDir = Platform::getCwd(true);
chdir($dir);
}
break;
}
$dir = dirname($dir);
}
}

$needsSudoCheck = !Platform::isWindows()
&& function_exists('exec')
&& !Platform::getEnv('COMPOSER_ALLOW_SUPERUSER')
&& (ini_get('open_basedir') || !file_exists('/.dockerenv'));
$isNonAllowedRoot = false;


if ($needsSudoCheck) {
$isNonAllowedRoot = function_exists('posix_getuid') && posix_getuid() === 0;

if ($isNonAllowedRoot) {
if ($uid = (int) Platform::getEnv('SUDO_UID')) {


Silencer::call('exec', "sudo -u \\#{$uid} sudo -K > /dev/null 2>&1");
}
}


Silencer::call('exec', 'sudo -K > /dev/null 2>&1');
}



$mayNeedPluginCommand = false === $input->hasParameterOption(['--version', '-V'])
&& (

false === $commandName

|| in_array($commandName, ['', 'list', 'help'], true)


|| ($commandName === '_complete' && !$isNonAllowedRoot)
);

if ($mayNeedPluginCommand && !$this->disablePluginsByDefault && !$this->hasPluginCommands) {


if ($isNonAllowedRoot) {
$io->writeError('<warning>Do not run Composer as root/super user! See https://getcomposer.org/root for details</warning>');

if ($io->isInteractive() && $io->askConfirmation('<info>Continue as root/super user</info> [<comment>yes</comment>]? ')) {

$isNonAllowedRoot = false;
} else {
$io->writeError('<warning>Aborting as no plugin should be loaded if running as super user is not explicitly allowed</warning>');

return 1;
}
}

try {
foreach ($this->getPluginCommands() as $command) {
if ($this->has($command->getName())) {
$io->writeError('<warning>Plugin command '.$command->getName().' ('.get_class($command).') would override a Composer command and has been skipped</warning>');
} else {
$this->add($command);
}
}
} catch (NoSslException $e) {

} catch (ParsingException $e) {
$details = $e->getDetails();

$file = realpath(Factory::getComposerFile());

$line = null;
if ($details && isset($details['line'])) {
$line = $details['line'];
}

$ghe = new GithubActionError($this->io);
$ghe->emit($e->getMessage(), $file, $line);

throw $e;
}

$this->hasPluginCommands = true;
}

if ($isNonAllowedRoot && !$io->isInteractive()) {
$io->writeError('<error>Composer plugins have been disabled for safety in this non-interactive session. Set COMPOSER_ALLOW_SUPERUSER=1 if you want to allow plugins to run as root/super user.</error>');
$this->disablePluginsByDefault = true;
}


$isProxyCommand = false;
if ($name = $this->getCommandName($input)) {
try {
$command = $this->find($name);
$commandName = $command->getName();
$isProxyCommand = ($command instanceof Command\BaseCommand && $command->isProxyCommand());
} catch (\InvalidArgumentException $e) {
}
}

if (!$isProxyCommand) {
$io->writeError(sprintf(
'Running %s (%s) with %s on %s',
Composer::getVersion(),
Composer::RELEASE_DATE,
defined('HHVM_VERSION') ? 'HHVM '.HHVM_VERSION : 'PHP '.PHP_VERSION,
function_exists('php_uname') ? php_uname('s') . ' / ' . php_uname('r') : 'Unknown OS'
), true, IOInterface::DEBUG);

if (PHP_VERSION_ID < 70205) {
$io->writeError('<warning>Composer supports PHP 7.2.5 and above, you will most likely encounter problems with your PHP '.PHP_VERSION.'. Upgrading is strongly recommended but you can use Composer 2.2.x LTS as a fallback.</warning>');
}

if (XdebugHandler::isXdebugActive() && !Platform::getEnv('COMPOSER_DISABLE_XDEBUG_WARN')) {
$io->writeError('<warning>Composer is operating slower than normal because you have Xdebug enabled. See https://getcomposer.org/xdebug</warning>');
}

if (defined('COMPOSER_DEV_WARNING_TIME') && $commandName !== 'self-update' && $commandName !== 'selfupdate' && time() > COMPOSER_DEV_WARNING_TIME) {
$io->writeError(sprintf('<warning>Warning: This development build of Composer is over 60 days old. It is recommended to update it by running "%s self-update" to get the latest version.</warning>', $_SERVER['PHP_SELF']));
}

if ($isNonAllowedRoot) {
if ($commandName !== 'self-update' && $commandName !== 'selfupdate' && $commandName !== '_complete') {
$io->writeError('<warning>Do not run Composer as root/super user! See https://getcomposer.org/root for details</warning>');

if ($io->isInteractive()) {
if (!$io->askConfirmation('<info>Continue as root/super user</info> [<comment>yes</comment>]? ')) {
return 1;
}
}
}
}


Silencer::call(static function () use ($io): void {
$tempfile = sys_get_temp_dir() . '/temp-' . md5(microtime());
if (!(file_put_contents($tempfile, __FILE__) && (file_get_contents($tempfile) === __FILE__) && unlink($tempfile) && !file_exists($tempfile))) {
$io->writeError(sprintf('<error>PHP temp directory (%s) does not exist or is not writable to Composer. Set sys_temp_dir in your php.ini</error>', sys_get_temp_dir()));
}
});


$file = Factory::getComposerFile();
if (is_file($file) && Filesystem::isReadable($file) && is_array($composer = json_decode(file_get_contents($file), true))) {
if (isset($composer['scripts']) && is_array($composer['scripts'])) {
foreach ($composer['scripts'] as $script => $dummy) {
if (!defined('Composer\Script\ScriptEvents::'.str_replace('-', '_', strtoupper($script)))) {
if ($this->has($script)) {
$io->writeError('<warning>A script named '.$script.' would override a Composer command and has been skipped</warning>');
} else {
$description = null;

if (isset($composer['scripts-descriptions'][$script])) {
$description = $composer['scripts-descriptions'][$script];
}

$this->add(new Command\ScriptAliasCommand($script, $description));
}
}
}
}
}
}

try {
if ($input->hasParameterOption('--profile')) {
$startTime = microtime(true);
$this->io->enableDebugging($startTime);
}

$result = parent::doRun($input, $output);


if (isset($oldWorkingDir) && '' !== $oldWorkingDir) {
Silencer::call('chdir', $oldWorkingDir);
}

if (isset($startTime)) {
$io->writeError('<info>Memory usage: '.round(memory_get_usage() / 1024 / 1024, 2).'MiB (peak: '.round(memory_get_peak_usage() / 1024 / 1024, 2).'MiB), time: '.round(microtime(true) - $startTime, 2).'s');
}

return $result;
} catch (ScriptExecutionException $e) {
return $e->getCode();
} catch (\Throwable $e) {
$ghe = new GithubActionError($this->io);
$ghe->emit($e->getMessage());

$this->hintCommonErrors($e, $output);



if (!$e instanceof \Exception) {
if ($output instanceof ConsoleOutputInterface) {
$this->renderThrowable($e, $output->getErrorOutput());
} else {
$this->renderThrowable($e, $output);
}

return max(1, $e->getCode());
}

throw $e;
} finally {
restore_error_handler();
}
}





private function getNewWorkingDir(InputInterface $input): ?string
{

$workingDir = $input->getParameterOption(['--working-dir', '-d'], null, true);
if (null !== $workingDir && !is_dir($workingDir)) {
throw new \RuntimeException('Invalid working directory specified, '.$workingDir.' does not exist.');
}

return $workingDir;
}

private function hintCommonErrors(\Throwable $exception, OutputInterface $output): void
{
$io = $this->getIO();

if ((get_class($exception) === LogicException::class || $exception instanceof \Error) && $output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE) {
$output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
}

Silencer::suppress();
try {
$composer = $this->getComposer(false, true);
if (null !== $composer && function_exists('disk_free_space')) {
$config = $composer->getConfig();

$minSpaceFree = 100 * 1024 * 1024;
if ((($df = disk_free_space($dir = $config->get('home'))) !== false && $df < $minSpaceFree)
|| (($df = disk_free_space($dir = $config->get('vendor-dir'))) !== false && $df < $minSpaceFree)
|| (($df = disk_free_space($dir = sys_get_temp_dir())) !== false && $df < $minSpaceFree)
) {
$io->writeError('<error>The disk hosting '.$dir.' has less than 100MiB of free space, this may be the cause of the following exception</error>', true, IOInterface::QUIET);
}
}
} catch (\Exception $e) {
}
Silencer::restore();

if (Platform::isWindows() && false !== strpos($exception->getMessage(), 'The system cannot find the path specified')) {
$io->writeError('<error>The following exception may be caused by a stale entry in your cmd.exe AutoRun</error>', true, IOInterface::QUIET);
$io->writeError('<error>Check https://getcomposer.org/doc/articles/troubleshooting.md#-the-system-cannot-find-the-path-specified-windows- for details</error>', true, IOInterface::QUIET);
}

if (false !== strpos($exception->getMessage(), 'fork failed - Cannot allocate memory')) {
$io->writeError('<error>The following exception is caused by a lack of memory or swap, or not having swap configured</error>', true, IOInterface::QUIET);
$io->writeError('<error>Check https://getcomposer.org/doc/articles/troubleshooting.md#proc-open-fork-failed-errors for details</error>', true, IOInterface::QUIET);
}

if ($exception instanceof ProcessTimedOutException) {
$io->writeError('<error>The following exception is caused by a process timeout</error>', true, IOInterface::QUIET);
$io->writeError('<error>Check https://getcomposer.org/doc/06-config.md#process-timeout for details</error>', true, IOInterface::QUIET);
}

$hints = HttpDownloader::getExceptionHints($exception);
if (null !== $hints && count($hints) > 0) {
foreach ($hints as $hint) {
$io->writeError($hint, true, IOInterface::QUIET);
}
}
}






public function getComposer(bool $required = true, ?bool $disablePlugins = null, ?bool $disableScripts = null): ?Composer
{
if (null === $disablePlugins) {
$disablePlugins = $this->disablePluginsByDefault;
}
if (null === $disableScripts) {
$disableScripts = $this->disableScriptsByDefault;
}

if (null === $this->composer) {
try {
$this->composer = Factory::create(Platform::isInputCompletionProcess() ? new NullIO() : $this->io, null, $disablePlugins, $disableScripts);
} catch (\InvalidArgumentException $e) {
if ($required) {
$this->io->writeError($e->getMessage());
if ($this->areExceptionsCaught()) {
exit(1);
}
throw $e;
}
} catch (JsonValidationException $e) {
if ($required) {
throw $e;
}
} catch (RuntimeException $e) {
if ($required) {
throw $e;
}
}
}

return $this->composer;
}




public function resetComposer(): void
{
$this->composer = null;
if (method_exists($this->getIO(), 'resetAuthentications')) {
$this->getIO()->resetAuthentications();
}
}

public function getIO(): IOInterface
{
return $this->io;
}

public function getHelp(): string
{
return self::$logo . parent::getHelp();
}





protected function getDefaultCommands(): array
{
$commands = array_merge(parent::getDefaultCommands(), [
new Command\AboutCommand(),
new Command\ConfigCommand(),
new Command\DependsCommand(),
new Command\ProhibitsCommand(),
new Command\InitCommand(),
new Command\InstallCommand(),
new Command\CreateProjectCommand(),
new Command\UpdateCommand(),
new Command\SearchCommand(),
new Command\ValidateCommand(),
new Command\AuditCommand(),
new Command\ShowCommand(),
new Command\SuggestsCommand(),
new Command\RequireCommand(),
new Command\DumpAutoloadCommand(),
new Command\StatusCommand(),
new Command\ArchiveCommand(),
new Command\DiagnoseCommand(),
new Command\RunScriptCommand(),
new Command\LicensesCommand(),
new Command\GlobalCommand(),
new Command\ClearCacheCommand(),
new Command\RemoveCommand(),
new Command\HomeCommand(),
new Command\ExecCommand(),
new Command\OutdatedCommand(),
new Command\CheckPlatformReqsCommand(),
new Command\FundCommand(),
new Command\ReinstallCommand(),
new Command\BumpCommand(),
]);

if (strpos(__FILE__, 'phar:') === 0 || '1' === Platform::getEnv('COMPOSER_TESTS_ARE_RUNNING')) {
$commands[] = new Command\SelfUpdateCommand();
}

return $commands;
}

public function getLongVersion(): string
{
$branchAliasString = '';
if (Composer::BRANCH_ALIAS_VERSION && Composer::BRANCH_ALIAS_VERSION !== '@package_branch_alias_version'.'@') {
$branchAliasString = sprintf(' (%s)', Composer::BRANCH_ALIAS_VERSION);
}

return sprintf(
'<info>%s</info> version <comment>%s%s</comment> %s',
$this->getName(),
$this->getVersion(),
$branchAliasString,
Composer::RELEASE_DATE
);
}

protected function getDefaultInputDefinition(): InputDefinition
{
$definition = parent::getDefaultInputDefinition();
$definition->addOption(new InputOption('--profile', null, InputOption::VALUE_NONE, 'Display timing and memory usage information'));
$definition->addOption(new InputOption('--no-plugins', null, InputOption::VALUE_NONE, 'Whether to disable plugins.'));
$definition->addOption(new InputOption('--no-scripts', null, InputOption::VALUE_NONE, 'Skips the execution of all scripts defined in composer.json file.'));
$definition->addOption(new InputOption('--working-dir', '-d', InputOption::VALUE_REQUIRED, 'If specified, use the given directory as working directory.'));
$definition->addOption(new InputOption('--no-cache', null, InputOption::VALUE_NONE, 'Prevent use of the cache'));

return $definition;
}




private function getPluginCommands(): array
{
$commands = [];

$composer = $this->getComposer(false, false);
if (null === $composer) {
$composer = Factory::createGlobal($this->io, $this->disablePluginsByDefault, $this->disableScriptsByDefault);
}

if (null !== $composer) {
$pm = $composer->getPluginManager();
foreach ($pm->getPluginCapabilities('Composer\Plugin\Capability\CommandProvider', ['composer' => $composer, 'io' => $this->io]) as $capability) {
$newCommands = $capability->getCommands();
if (!is_array($newCommands)) {
throw new \UnexpectedValueException('Plugin capability '.get_class($capability).' failed to return an array from getCommands');
}
foreach ($newCommands as $command) {
if (!$command instanceof Command\BaseCommand) {
throw new \UnexpectedValueException('Plugin capability '.get_class($capability).' returned an invalid value, we expected an array of Composer\Command\BaseCommand objects');
}
}
$commands = array_merge($commands, $newCommands);
}
}

return $commands;
}






public function getInitialWorkingDirectory()
{
return $this->initialWorkingDirectory;
}




private function getUseParentDirConfigValue()
{
$config = Factory::createConfig($this->io);

return $config->get('use-parent-dir');
}
}
