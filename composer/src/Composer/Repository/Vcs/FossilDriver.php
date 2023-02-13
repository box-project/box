<?php declare(strict_types=1);











namespace Composer\Repository\Vcs;

use Composer\Cache;
use Composer\Config;
use Composer\Pcre\Preg;
use Composer\Util\ProcessExecutor;
use Composer\Util\Filesystem;
use Composer\IO\IOInterface;




class FossilDriver extends VcsDriver
{

protected $tags;

protected $branches;

protected $rootIdentifier = null;

protected $repoFile = null;

protected $checkoutDir;




public function initialize(): void
{

$this->checkFossil();


$this->config->prohibitUrlByConfig($this->url, $this->io);



if (Filesystem::isLocalPath($this->url) && is_dir($this->url)) {
$this->checkoutDir = $this->url;
} else {
if (!Cache::isUsable($this->config->get('cache-repo-dir')) || !Cache::isUsable($this->config->get('cache-vcs-dir'))) {
throw new \RuntimeException('FossilDriver requires a usable cache directory, and it looks like you set it to be disabled');
}

$localName = Preg::replace('{[^a-z0-9]}i', '-', $this->url);
$this->repoFile = $this->config->get('cache-repo-dir') . '/' . $localName . '.fossil';
$this->checkoutDir = $this->config->get('cache-vcs-dir') . '/' . $localName . '/';

$this->updateLocalRepo();
}

$this->getTags();
$this->getBranches();
}




protected function checkFossil(): void
{
if (0 !== $this->process->execute('fossil version', $ignoredOutput)) {
throw new \RuntimeException("fossil was not found, check that it is installed and in your PATH env.\n\n" . $this->process->getErrorOutput());
}
}




protected function updateLocalRepo(): void
{
$fs = new Filesystem();
$fs->ensureDirectoryExists($this->checkoutDir);

if (!is_writable(dirname($this->checkoutDir))) {
throw new \RuntimeException('Can not clone '.$this->url.' to access package information. The "'.$this->checkoutDir.'" directory is not writable by the current user.');
}


if (is_file($this->repoFile) && is_dir($this->checkoutDir) && 0 === $this->process->execute('fossil info', $output, $this->checkoutDir)) {
if (0 !== $this->process->execute('fossil pull', $output, $this->checkoutDir)) {
$this->io->writeError('<error>Failed to update '.$this->url.', package information from this repository may be outdated ('.$this->process->getErrorOutput().')</error>');
}
} else {

$fs->removeDirectory($this->checkoutDir);
$fs->remove($this->repoFile);

$fs->ensureDirectoryExists($this->checkoutDir);

if (0 !== $this->process->execute(sprintf('fossil clone -- %s %s', ProcessExecutor::escape($this->url), ProcessExecutor::escape($this->repoFile)), $output)) {
$output = $this->process->getErrorOutput();

throw new \RuntimeException('Failed to clone '.$this->url.' to repository ' . $this->repoFile . "\n\n" .$output);
}

if (0 !== $this->process->execute(sprintf('fossil open --nested -- %s', ProcessExecutor::escape($this->repoFile)), $output, $this->checkoutDir)) {
$output = $this->process->getErrorOutput();

throw new \RuntimeException('Failed to open repository '.$this->repoFile.' in ' . $this->checkoutDir . "\n\n" .$output);
}
}
}




public function getRootIdentifier(): string
{
if (null === $this->rootIdentifier) {
$this->rootIdentifier = 'trunk';
}

return $this->rootIdentifier;
}




public function getUrl(): string
{
return $this->url;
}




public function getSource(string $identifier): array
{
return ['type' => 'fossil', 'url' => $this->getUrl(), 'reference' => $identifier];
}




public function getDist(string $identifier): ?array
{
return null;
}




public function getFileContent(string $file, string $identifier): ?string
{
$command = sprintf('fossil cat -r %s -- %s', ProcessExecutor::escape($identifier), ProcessExecutor::escape($file));
$this->process->execute($command, $content, $this->checkoutDir);

if (!trim($content)) {
return null;
}

return $content;
}




public function getChangeDate(string $identifier): ?\DateTimeImmutable
{
$this->process->execute('fossil finfo -b -n 1 composer.json', $output, $this->checkoutDir);
[, $date] = explode(' ', trim($output), 3);

return new \DateTimeImmutable($date, new \DateTimeZone('UTC'));
}




public function getTags(): array
{
if (null === $this->tags) {
$tags = [];

$this->process->execute('fossil tag list', $output, $this->checkoutDir);
foreach ($this->process->splitLines($output) as $tag) {
$tags[$tag] = $tag;
}

$this->tags = $tags;
}

return $this->tags;
}




public function getBranches(): array
{
if (null === $this->branches) {
$branches = [];

$this->process->execute('fossil branch list', $output, $this->checkoutDir);
foreach ($this->process->splitLines($output) as $branch) {
$branch = trim(Preg::replace('/^\*/', '', trim($branch)));
$branches[$branch] = $branch;
}

$this->branches = $branches;
}

return $this->branches;
}




public static function supports(IOInterface $io, Config $config, string $url, bool $deep = false): bool
{
if (Preg::isMatch('#(^(?:https?|ssh)://(?:[^@]@)?(?:chiselapp\.com|fossil\.))#i', $url)) {
return true;
}

if (Preg::isMatch('!/fossil/|\.fossil!', $url)) {
return true;
}


if (Filesystem::isLocalPath($url)) {
$url = Filesystem::getPlatformPath($url);
if (!is_dir($url)) {
return false;
}

$process = new ProcessExecutor($io);

if ($process->execute('fossil info', $output, $url) === 0) {
return true;
}
}

return false;
}
}
