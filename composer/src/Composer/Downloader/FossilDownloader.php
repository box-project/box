<?php declare(strict_types=1);











namespace Composer\Downloader;

use React\Promise\PromiseInterface;
use Composer\Package\PackageInterface;
use Composer\Pcre\Preg;
use Composer\Util\ProcessExecutor;




class FossilDownloader extends VcsDownloader
{



protected function doDownload(PackageInterface $package, string $path, string $url, ?PackageInterface $prevPackage = null): PromiseInterface
{
return \React\Promise\resolve(null);
}




protected function doInstall(PackageInterface $package, string $path, string $url): PromiseInterface
{

$this->config->prohibitUrlByConfig($url, $this->io);

$url = ProcessExecutor::escape($url);
$ref = ProcessExecutor::escape($package->getSourceReference());
$repoFile = $path . '.fossil';
$this->io->writeError("Cloning ".$package->getSourceReference());
$command = sprintf('fossil clone -- %s %s', $url, ProcessExecutor::escape($repoFile));
if (0 !== $this->process->execute($command, $ignoredOutput)) {
throw new \RuntimeException('Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput());
}
$command = sprintf('fossil open --nested -- %s', ProcessExecutor::escape($repoFile));
if (0 !== $this->process->execute($command, $ignoredOutput, realpath($path))) {
throw new \RuntimeException('Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput());
}
$command = sprintf('fossil update -- %s', $ref);
if (0 !== $this->process->execute($command, $ignoredOutput, realpath($path))) {
throw new \RuntimeException('Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput());
}

return \React\Promise\resolve(null);
}




protected function doUpdate(PackageInterface $initial, PackageInterface $target, string $path, string $url): PromiseInterface
{

$this->config->prohibitUrlByConfig($url, $this->io);

$ref = ProcessExecutor::escape($target->getSourceReference());
$this->io->writeError(" Updating to ".$target->getSourceReference());

if (!$this->hasMetadataRepository($path)) {
throw new \RuntimeException('The .fslckout file is missing from '.$path.', see https://getcomposer.org/commit-deps for more information');
}

$command = sprintf('fossil pull && fossil up %s', $ref);
if (0 !== $this->process->execute($command, $ignoredOutput, realpath($path))) {
throw new \RuntimeException('Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput());
}

return \React\Promise\resolve(null);
}




public function getLocalChanges(PackageInterface $package, string $path): ?string
{
if (!$this->hasMetadataRepository($path)) {
return null;
}

$this->process->execute('fossil changes', $output, realpath($path));

$output = trim($output);

return strlen($output) > 0 ? $output : null;
}




protected function getCommitLogs(string $fromReference, string $toReference, string $path): string
{
$command = sprintf('fossil timeline -t ci -W 0 -n 0 before %s', ProcessExecutor::escape($toReference));

if (0 !== $this->process->execute($command, $output, realpath($path))) {
throw new \RuntimeException('Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput());
}

$log = '';
$match = '/\d\d:\d\d:\d\d\s+\[' . $toReference . '\]/';

foreach ($this->process->splitLines($output) as $line) {
if (Preg::isMatch($match, $line)) {
break;
}
$log .= $line;
}

return $log;
}




protected function hasMetadataRepository(string $path): bool
{
return is_file($path . '/.fslckout') || is_file($path . '/_FOSSIL_');
}
}
