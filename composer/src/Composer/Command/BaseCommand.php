<?php declare(strict_types=1);











namespace Composer\Command;

use Composer\Composer;
use Composer\Config;
use Composer\Console\Application;
use Composer\Console\Input\InputArgument;
use Composer\Console\Input\InputOption;
use Composer\Factory;
use Composer\Filter\PlatformRequirementFilter\PlatformRequirementFilterFactory;
use Composer\Filter\PlatformRequirementFilter\PlatformRequirementFilterInterface;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Plugin\PreCommandRunEvent;
use Composer\Package\Version\VersionParser;
use Composer\Plugin\PluginEvents;
use Composer\Advisory\Auditor;
use Composer\Util\Platform;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Terminal;







abstract class BaseCommand extends Command
{



private $composer;




private $io;




public function getApplication(): Application
{
$application = parent::getApplication();
if (!$application instanceof Application) {
throw new \RuntimeException('Composer commands can only work with an '.Application::class.' instance set');
}

return $application;
}









public function getComposer(bool $required = true, ?bool $disablePlugins = null, ?bool $disableScripts = null)
{
if ($required) {
return $this->requireComposer($disablePlugins, $disableScripts);
}

return $this->tryComposer($disablePlugins, $disableScripts);
}










public function requireComposer(?bool $disablePlugins = null, ?bool $disableScripts = null): Composer
{
if (null === $this->composer) {
$application = parent::getApplication();
if ($application instanceof Application) {
$this->composer = $application->getComposer(true, $disablePlugins, $disableScripts);
assert($this->composer instanceof Composer);
} else {
throw new \RuntimeException(
'Could not create a Composer\Composer instance, you must inject '.
'one if this command is not used with a Composer\Console\Application instance'
);
}
}

return $this->composer;
}









public function tryComposer(?bool $disablePlugins = null, ?bool $disableScripts = null): ?Composer
{
if (null === $this->composer) {
$application = parent::getApplication();
if ($application instanceof Application) {
$this->composer = $application->getComposer(false, $disablePlugins, $disableScripts);
}
}

return $this->composer;
}




public function setComposer(Composer $composer)
{
$this->composer = $composer;
}






public function resetComposer()
{
$this->composer = null;
$this->getApplication()->resetComposer();
}








public function isProxyCommand()
{
return false;
}




public function getIO()
{
if (null === $this->io) {
$application = parent::getApplication();
if ($application instanceof Application) {
$this->io = $application->getIO();
} else {
$this->io = new NullIO();
}
}

return $this->io;
}




public function setIO(IOInterface $io)
{
$this->io = $io;
}








public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
{
$definition = $this->getDefinition();
$name = (string) $input->getCompletionName();
if (CompletionInput::TYPE_OPTION_VALUE === $input->getCompletionType()
&& $definition->hasOption($name)
&& ($option = $definition->getOption($name)) instanceof InputOption
) {
$option->complete($input, $suggestions);
} elseif (CompletionInput::TYPE_ARGUMENT_VALUE === $input->getCompletionType()
&& $definition->hasArgument($name)
&& ($argument = $definition->getArgument($name)) instanceof InputArgument
) {
$argument->complete($input, $suggestions);
} else {
parent::complete($input, $suggestions);
}
}






protected function initialize(InputInterface $input, OutputInterface $output)
{

$disablePlugins = $input->hasParameterOption('--no-plugins');
$disableScripts = $input->hasParameterOption('--no-scripts');
if ($this instanceof SelfUpdateCommand) {
$disablePlugins = true;
$disableScripts = true;
}

$composer = $this->tryComposer($disablePlugins, $disableScripts);
$io = $this->getIO();

if (null === $composer) {
$composer = Factory::createGlobal($this->getIO(), $disablePlugins, $disableScripts);
}
if ($composer) {
$preCommandRunEvent = new PreCommandRunEvent(PluginEvents::PRE_COMMAND_RUN, $input, $this->getName());
$composer->getEventDispatcher()->dispatch($preCommandRunEvent->getName(), $preCommandRunEvent);
}

if (true === $input->hasParameterOption(['--no-ansi']) && $input->hasOption('no-progress')) {
$input->setOption('no-progress', true);
}

$envOptions = [
'COMPOSER_NO_AUDIT' => ['no-audit'],
'COMPOSER_NO_DEV' => ['no-dev', 'update-no-dev'],
'COMPOSER_PREFER_STABLE' => ['prefer-stable'],
'COMPOSER_PREFER_LOWEST' => ['prefer-lowest'],
];
foreach ($envOptions as $envName => $optionNames) {
foreach ($optionNames as $optionName) {
if (true === $input->hasOption($optionName)) {
if (false === $input->getOption($optionName) && (bool) Platform::getEnv($envName)) {
$input->setOption($optionName, true);
}
}
}
}

if (true === $input->hasOption('ignore-platform-reqs')) {
if (!$input->getOption('ignore-platform-reqs') && (bool) Platform::getEnv('COMPOSER_IGNORE_PLATFORM_REQS')) {
$input->setOption('ignore-platform-reqs', true);

$io->writeError('<warning>COMPOSER_IGNORE_PLATFORM_REQS is set. You may experience unexpected errors.</warning>');
}
}

if (true === $input->hasOption('ignore-platform-req') && (!$input->hasOption('ignore-platform-reqs') || !$input->getOption('ignore-platform-reqs'))) {
$ignorePlatformReqEnv = Platform::getEnv('COMPOSER_IGNORE_PLATFORM_REQ');
if (0 === count($input->getOption('ignore-platform-req')) && is_string($ignorePlatformReqEnv) && '' !== $ignorePlatformReqEnv) {
$input->setOption('ignore-platform-req', explode(',', $ignorePlatformReqEnv));

$io->writeError('<warning>COMPOSER_IGNORE_PLATFORM_REQ is set to ignore '.$ignorePlatformReqEnv.'. You may experience unexpected errors.</warning>');
}
}

parent::initialize($input, $output);
}






protected function getPreferredInstallOptions(Config $config, InputInterface $input, bool $keepVcsRequiresPreferSource = false)
{
$preferSource = false;
$preferDist = false;

switch ($config->get('preferred-install')) {
case 'source':
$preferSource = true;
break;
case 'dist':
$preferDist = true;
break;
case 'auto':
default:

break;
}

if (!$input->hasOption('prefer-dist') || !$input->hasOption('prefer-source')) {
return [$preferSource, $preferDist];
}

if ($input->hasOption('prefer-install') && is_string($input->getOption('prefer-install'))) {
if ($input->getOption('prefer-source')) {
throw new \InvalidArgumentException('--prefer-source can not be used together with --prefer-install');
}
if ($input->getOption('prefer-dist')) {
throw new \InvalidArgumentException('--prefer-dist can not be used together with --prefer-install');
}
switch ($input->getOption('prefer-install')) {
case 'dist':
$input->setOption('prefer-dist', true);
break;
case 'source':
$input->setOption('prefer-source', true);
break;
case 'auto':
$preferDist = false;
$preferSource = false;
break;
default:
throw new \UnexpectedValueException('--prefer-install accepts one of "dist", "source" or "auto", got '.$input->getOption('prefer-install'));
}
}

if ($input->getOption('prefer-source') || $input->getOption('prefer-dist') || ($keepVcsRequiresPreferSource && $input->hasOption('keep-vcs') && $input->getOption('keep-vcs'))) {
$preferSource = $input->getOption('prefer-source') || ($keepVcsRequiresPreferSource && $input->hasOption('keep-vcs') && $input->getOption('keep-vcs'));
$preferDist = $input->getOption('prefer-dist');
}

return [$preferSource, $preferDist];
}

protected function getPlatformRequirementFilter(InputInterface $input): PlatformRequirementFilterInterface
{
if (!$input->hasOption('ignore-platform-reqs') || !$input->hasOption('ignore-platform-req')) {
throw new \LogicException('Calling getPlatformRequirementFilter from a command which does not define the --ignore-platform-req[s] flags is not permitted.');
}

if (true === $input->getOption('ignore-platform-reqs')) {
return PlatformRequirementFilterFactory::ignoreAll();
}

$ignores = $input->getOption('ignore-platform-req');
if (count($ignores) > 0) {
return PlatformRequirementFilterFactory::fromBoolOrList($ignores);
}

return PlatformRequirementFilterFactory::ignoreNothing();
}






protected function formatRequirements(array $requirements)
{
$requires = [];
$requirements = $this->normalizeRequirements($requirements);
foreach ($requirements as $requirement) {
if (!isset($requirement['version'])) {
throw new \UnexpectedValueException('Option '.$requirement['name'] .' is missing a version constraint, use e.g. '.$requirement['name'].':^1.0');
}
$requires[$requirement['name']] = $requirement['version'];
}

return $requires;
}






protected function normalizeRequirements(array $requirements)
{
$parser = new VersionParser();

return $parser->parseNameVersionPairs($requirements);
}






protected function renderTable(array $table, OutputInterface $output)
{
$renderer = new Table($output);
$renderer->setStyle('compact');
$renderer->setRows($table)->render();
}




protected function getTerminalWidth()
{
$terminal = new Terminal();
$width = $terminal->getWidth();

if (Platform::isWindows()) {
$width--;
} else {
$width = max(80, $width);
}

return $width;
}






protected function getAuditFormat(InputInterface $input, string $optName = 'audit-format'): string
{
if (!$input->hasOption($optName)) {
throw new \LogicException('This should not be called on a Command which has no '.$optName.' option defined.');
}

$val = $input->getOption($optName);
if (!in_array($val, Auditor::FORMATS, true)) {
throw new \InvalidArgumentException('--'.$optName.' must be one of '.implode(', ', Auditor::FORMATS).'.');
}

return $val;
}
}
