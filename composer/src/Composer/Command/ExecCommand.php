<?php declare(strict_types=1);











namespace Composer\Command;

use Symfony\Component\Console\Input\InputInterface;
use Composer\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Console\Input\InputArgument;




class ExecCommand extends BaseCommand
{



protected function configure()
{
$this
->setName('exec')
->setDescription('Executes a vendored binary/script')
->setDefinition([
new InputOption('list', 'l', InputOption::VALUE_NONE),
new InputArgument('binary', InputArgument::OPTIONAL, 'The binary to run, e.g. phpunit', null, function () {
return $this->getBinaries(false);
}),
new InputArgument(
'args',
InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
'Arguments to pass to the binary. Use <info>--</info> to separate from composer arguments'
),
])
->setHelp(
<<<EOT
Executes a vendored binary/script.

Read more at https://getcomposer.org/doc/03-cli.md#exec
EOT
)
;
}

protected function interact(InputInterface $input, OutputInterface $output): void
{
$binaries = $this->getBinaries(false);
if (count($binaries) === 0) {
return;
}

if ($input->getArgument('binary') !== null || $input->getOption('list')) {
return;
}

$io = $this->getIO();

$binary = $io->select(
'Binary to run: ',
$binaries,
'',
1,
'Invalid binary name "%s"'
);

$input->setArgument('binary', $binaries[$binary]);
}

protected function execute(InputInterface $input, OutputInterface $output)
{
$composer = $this->requireComposer();
if ($input->getOption('list') || null === $input->getArgument('binary')) {
$bins = $this->getBinaries(true);
if ([] === $bins) {
$binDir = $composer->getConfig()->get('bin-dir');

throw new \RuntimeException("No binaries found in composer.json or in bin-dir ($binDir)");
}

$this->getIO()->write(
<<<EOT
<comment>Available binaries:</comment>
EOT
);

foreach ($bins as $bin) {
$this->getIO()->write(
<<<EOT
<info>- $bin</info>
EOT
);
}

return 0;
}

$binary = $input->getArgument('binary');

$dispatcher = $composer->getEventDispatcher();
$dispatcher->addListener('__exec_command', $binary);




if (getcwd() !== $this->getApplication()->getInitialWorkingDirectory() && $this->getApplication()->getInitialWorkingDirectory() !== false) {
try {
chdir($this->getApplication()->getInitialWorkingDirectory());
} catch (\Exception $e) {
throw new \RuntimeException('Could not switch back to working directory "'.$this->getApplication()->getInitialWorkingDirectory().'"', 0, $e);
}
}

return $dispatcher->dispatchScript('__exec_command', true, $input->getArgument('args'));
}




private function getBinaries(bool $forDisplay): array
{
$composer = $this->requireComposer();
$binDir = $composer->getConfig()->get('bin-dir');
$bins = glob($binDir . '/*');
$localBins = $composer->getPackage()->getBinaries();
if ($forDisplay) {
$localBins = array_map(static function ($e) {
return "$e (local)";
}, $localBins);
}

$binaries = [];
foreach (array_merge($bins, $localBins) as $bin) {

if (isset($previousBin) && $bin === $previousBin.'.bat') {
continue;
}

$previousBin = $bin;
$binaries[] = basename($bin);
}

return $binaries;
}
}
