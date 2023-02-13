<?php declare(strict_types=1);











namespace Composer\Command;

use Composer\Factory;
use Composer\Pcre\Preg;
use Composer\Util\Filesystem;
use Composer\Util\Platform;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputInterface;
use Composer\Console\Input\InputArgument;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;




class GlobalCommand extends BaseCommand
{
public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
{
$application = $this->getApplication();
if ($input->mustSuggestArgumentValuesFor('command-name')) {
$suggestions->suggestValues(array_values(array_filter(array_map(static function (Command $command) {
return $command->isHidden() ? null : $command->getName();
}, $application->all()))));

return;
}

if ($application->has($commandName = $input->getArgument('command-name'))) {
$input = $this->prepareSubcommandInput($input, true);
$input = CompletionInput::fromString($input->__toString(), 2);
$command = $application->find($commandName);
$command->mergeApplicationDefinition();

$input->bind($command->getDefinition());
$command->complete($input, $suggestions);
}
}

protected function configure(): void
{
$this
->setName('global')
->setDescription('Allows running commands in the global composer dir ($COMPOSER_HOME)')
->setDefinition([
new InputArgument('command-name', InputArgument::REQUIRED, ''),
new InputArgument('args', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, ''),
])
->setHelp(
<<<EOT
Use this command as a wrapper to run other Composer commands
within the global context of COMPOSER_HOME.

You can use this to install CLI utilities globally, all you need
is to add the COMPOSER_HOME/vendor/bin dir to your PATH env var.

COMPOSER_HOME is c:\Users\<user>\AppData\Roaming\Composer on Windows
and /home/<user>/.composer on unix systems.

If your system uses freedesktop.org standards, then it will first check
XDG_CONFIG_HOME or default to /home/<user>/.config/composer

Note: This path may vary depending on customizations to bin-dir in
composer.json or the environmental variable COMPOSER_BIN_DIR.

Read more at https://getcomposer.org/doc/03-cli.md#global
EOT
)
;
}




public function run(InputInterface $input, OutputInterface $output): int
{

if (!method_exists($input, '__toString')) { 
throw new \LogicException('Expected an Input instance that is stringable, got '.get_class($input));
}


$tokens = Preg::split('{\s+}', $input->__toString());
$args = [];
foreach ($tokens as $token) {
if ($token && $token[0] !== '-') {
$args[] = $token;
if (count($args) >= 2) {
break;
}
}
}


if (count($args) < 2) {
return parent::run($input, $output);
}

$input = $this->prepareSubcommandInput($input);

return $this->getApplication()->run($input, $output);
}

private function prepareSubcommandInput(InputInterface $input, bool $quiet = false): StringInput
{

if (!method_exists($input, '__toString')) { 
throw new \LogicException('Expected an Input instance that is stringable, got '.get_class($input));
}


if (Platform::getEnv('COMPOSER')) {
Platform::clearEnv('COMPOSER');
}


$config = Factory::createConfig();
$home = $config->get('home');

if (!is_dir($home)) {
$fs = new Filesystem();
$fs->ensureDirectoryExists($home);
if (!is_dir($home)) {
throw new \RuntimeException('Could not create home directory');
}
}

try {
chdir($home);
} catch (\Exception $e) {
throw new \RuntimeException('Could not switch to home directory "'.$home.'"', 0, $e);
}
if (!$quiet) {
$this->getIO()->writeError('<info>Changed current directory to '.$home.'</info>');
}


$input = new StringInput(Preg::replace('{\bg(?:l(?:o(?:b(?:a(?:l)?)?)?)?)?\b}', '', $input->__toString(), 1));
$this->getApplication()->resetComposer();

return $input;
}




public function isProxyCommand(): bool
{
return true;
}
}
