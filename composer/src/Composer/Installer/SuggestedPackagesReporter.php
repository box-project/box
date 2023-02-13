<?php declare(strict_types=1);











namespace Composer\Installer;

use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Pcre\Preg;
use Composer\Repository\InstalledRepository;
use Symfony\Component\Console\Formatter\OutputFormatter;






class SuggestedPackagesReporter
{
public const MODE_LIST = 1;
public const MODE_BY_PACKAGE = 2;
public const MODE_BY_SUGGESTION = 4;




protected $suggestedPackages = [];




private $io;

public function __construct(IOInterface $io)
{
$this->io = $io;
}




public function getPackages(): array
{
return $this->suggestedPackages;
}











public function addPackage(string $source, string $target, string $reason): SuggestedPackagesReporter
{
$this->suggestedPackages[] = [
'source' => $source,
'target' => $target,
'reason' => $reason,
];

return $this;
}




public function addSuggestionsFromPackage(PackageInterface $package): SuggestedPackagesReporter
{
$source = $package->getPrettyName();
foreach ($package->getSuggests() as $target => $reason) {
$this->addPackage(
$source,
$target,
$reason
);
}

return $this;
}










public function output(int $mode, ?InstalledRepository $installedRepo = null, ?PackageInterface $onlyDependentsOf = null): void
{
$suggestedPackages = $this->getFilteredSuggestions($installedRepo, $onlyDependentsOf);

$suggesters = [];
$suggested = [];
foreach ($suggestedPackages as $suggestion) {
$suggesters[$suggestion['source']][$suggestion['target']] = $suggestion['reason'];
$suggested[$suggestion['target']][$suggestion['source']] = $suggestion['reason'];
}
ksort($suggesters);
ksort($suggested);


if ($mode & self::MODE_LIST) {
foreach (array_keys($suggested) as $name) {
$this->io->write(sprintf('<info>%s</info>', $name));
}

return;
}


if ($mode & self::MODE_BY_PACKAGE) {
foreach ($suggesters as $suggester => $suggestions) {
$this->io->write(sprintf('<comment>%s</comment> suggests:', $suggester));

foreach ($suggestions as $suggestion => $reason) {
$this->io->write(sprintf(' - <info>%s</info>' . ($reason ? ': %s' : ''), $suggestion, $this->escapeOutput($reason)));
}
$this->io->write('');
}
}


if ($mode & self::MODE_BY_SUGGESTION) {

if ($mode & self::MODE_BY_PACKAGE) {
$this->io->write(str_repeat('-', 78));
}
foreach ($suggested as $suggestion => $suggesters) {
$this->io->write(sprintf('<comment>%s</comment> is suggested by:', $suggestion));

foreach ($suggesters as $suggester => $reason) {
$this->io->write(sprintf(' - <info>%s</info>' . ($reason ? ': %s' : ''), $suggester, $this->escapeOutput($reason)));
}
$this->io->write('');
}
}

if ($onlyDependentsOf) {
$allSuggestedPackages = $this->getFilteredSuggestions($installedRepo);
$diff = count($allSuggestedPackages) - count($suggestedPackages);
if ($diff) {
$this->io->write('<info>'.$diff.' additional suggestions</info> by transitive dependencies can be shown with <info>--all</info>');
}
}
}







public function outputMinimalistic(?InstalledRepository $installedRepo = null, ?PackageInterface $onlyDependentsOf = null): void
{
$suggestedPackages = $this->getFilteredSuggestions($installedRepo, $onlyDependentsOf);
if ($suggestedPackages) {
$this->io->writeError('<info>'.count($suggestedPackages).' package suggestions were added by new dependencies, use `composer suggest` to see details.</info>');
}
}






private function getFilteredSuggestions(?InstalledRepository $installedRepo = null, ?PackageInterface $onlyDependentsOf = null): array
{
$suggestedPackages = $this->getPackages();
$installedNames = [];
if (null !== $installedRepo && !empty($suggestedPackages)) {
foreach ($installedRepo->getPackages() as $package) {
$installedNames = array_merge(
$installedNames,
$package->getNames()
);
}
}

$sourceFilter = [];
if ($onlyDependentsOf) {
$sourceFilter = array_map(static function ($link): string {
return $link->getTarget();
}, array_merge($onlyDependentsOf->getRequires(), $onlyDependentsOf->getDevRequires()));
$sourceFilter[] = $onlyDependentsOf->getName();
}

$suggestions = [];
foreach ($suggestedPackages as $suggestion) {
if (in_array($suggestion['target'], $installedNames) || ($sourceFilter && !in_array($suggestion['source'], $sourceFilter))) {
continue;
}

$suggestions[] = $suggestion;
}

return $suggestions;
}

private function escapeOutput(string $string): string
{
return OutputFormatter::escape(
$this->removeControlCharacters($string)
);
}

private function removeControlCharacters(string $string): string
{
return Preg::replace(
'/[[:cntrl:]]/',
'',
str_replace("\n", ' ', $string)
);
}
}
