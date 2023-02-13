<?php declare(strict_types=1);











namespace Composer\Command;

use Composer\Package\Link;
use Composer\Semver\Constraint\Constraint;
use Symfony\Component\Console\Input\InputInterface;
use Composer\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RootPackageRepository;
use Composer\Repository\InstalledRepository;
use Composer\Json\JsonFile;

class CheckPlatformReqsCommand extends BaseCommand
{
protected function configure(): void
{
$this->setName('check-platform-reqs')
->setDescription('Check that platform requirements are satisfied')
->setDefinition([
new InputOption('no-dev', null, InputOption::VALUE_NONE, 'Disables checking of require-dev packages requirements.'),
new InputOption('lock', null, InputOption::VALUE_NONE, 'Checks requirements only from the lock file, not from installed packages.'),
new InputOption('format', 'f', InputOption::VALUE_REQUIRED, 'Format of the output: text or json', 'text', ['json', 'text']),
])
->setHelp(
<<<EOT
Checks that your PHP and extensions versions match the platform requirements of the installed packages.

Unlike update/install, this command will ignore config.platform settings and check the real platform packages so you can be certain you have the required platform dependencies.

<info>php composer.phar check-platform-reqs</info>

EOT
);
}

protected function execute(InputInterface $input, OutputInterface $output): int
{
$composer = $this->requireComposer();

$requires = [];
$removePackages = [];
if ($input->getOption('lock')) {
$this->getIO()->writeError('<info>Checking '.($input->getOption('no-dev') ? 'non-dev ' : '').'platform requirements using the lock file</info>');
$installedRepo = $composer->getLocker()->getLockedRepository(!$input->getOption('no-dev'));
} else {
$installedRepo = $composer->getRepositoryManager()->getLocalRepository();

if (!$installedRepo->getPackages()) {
$this->getIO()->writeError('<warning>No vendor dir present, checking '.($input->getOption('no-dev') ? 'non-dev ' : '').'platform requirements from the lock file</warning>');
$installedRepo = $composer->getLocker()->getLockedRepository(!$input->getOption('no-dev'));
} else {
if ($input->getOption('no-dev')) {
$removePackages = $installedRepo->getDevPackageNames();
}

$this->getIO()->writeError('<info>Checking '.($input->getOption('no-dev') ? 'non-dev ' : '').'platform requirements for packages in the vendor dir</info>');
}
}
if (!$input->getOption('no-dev')) {
$requires += $composer->getPackage()->getDevRequires();
}

foreach ($requires as $require => $link) {
$requires[$require] = [$link];
}

$installedRepo = new InstalledRepository([$installedRepo, new RootPackageRepository(clone $composer->getPackage())]);
foreach ($installedRepo->getPackages() as $package) {
if (in_array($package->getName(), $removePackages, true)) {
continue;
}
foreach ($package->getRequires() as $require => $link) {
$requires[$require][] = $link;
}
}

ksort($requires);

$installedRepo->addRepository(new PlatformRepository([], []));

$results = [];
$exitCode = 0;




foreach ($requires as $require => $links) {
if (PlatformRepository::isPlatformPackage($require)) {
$candidates = $installedRepo->findPackagesWithReplacersAndProviders($require);
if ($candidates) {
$reqResults = [];
foreach ($candidates as $candidate) {
$candidateConstraint = null;
if ($candidate->getName() === $require) {
$candidateConstraint = new Constraint('=', $candidate->getVersion());
$candidateConstraint->setPrettyString($candidate->getPrettyVersion());
} else {
foreach (array_merge($candidate->getProvides(), $candidate->getReplaces()) as $link) {
if ($link->getTarget() === $require) {
$candidateConstraint = $link->getConstraint();
break;
}
}
}


if (!$candidateConstraint) {
continue;
}

foreach ($links as $link) {
if (!$link->getConstraint()->matches($candidateConstraint)) {
$reqResults[] = [
$candidate->getName() === $require ? $candidate->getPrettyName() : $require,
$candidateConstraint->getPrettyString(),
$link,
'<error>failed</error>',
$candidate->getName() === $require ? '' : '<comment>provided by '.$candidate->getPrettyName().'</comment>',
];


continue 2;
}
}

$results[] = [
$candidate->getName() === $require ? $candidate->getPrettyName() : $require,
$candidateConstraint->getPrettyString(),
null,
'<info>success</info>',
$candidate->getName() === $require ? '' : '<comment>provided by '.$candidate->getPrettyName().'</comment>',
];


continue 2;
}


$results = array_merge($results, $reqResults);
$exitCode = max($exitCode, 1);

continue;
}

$results[] = [
$require,
'n/a',
$links[0],
'<error>missing</error>',
'',
];

$exitCode = max($exitCode, 2);
}
}

$this->printTable($output, $results, $input->getOption('format'));

return $exitCode;
}




protected function printTable(OutputInterface $output, array $results, string $format): void
{
$rows = [];
foreach ($results as $result) {



[$platformPackage, $version, $link, $status, $provider] = $result;

if ('json' === $format) {
$rows[] = [
"name" => $platformPackage,
"version" => $version,
"status" => strip_tags($status),
"failed_requirement" => $link instanceof Link ? [
'source' => $link->getSource(),
'type' => $link->getDescription(),
'target' => $link->getTarget(),
'constraint' => $link->getPrettyConstraint(),
] : null,
"provider" => $provider === '' ? null : strip_tags($provider),
];
} else {
$rows[] = [
$platformPackage,
$version,
$link,
$link ? sprintf('%s %s %s (%s)', $link->getSource(), $link->getDescription(), $link->getTarget(), $link->getPrettyConstraint()) : '',
rtrim($status.' '.$provider),
];
}
}

if ('json' === $format) {
$this->getIO()->write(JsonFile::encode($rows));
} else {
$this->renderTable($rows, $output);
}
}
}
