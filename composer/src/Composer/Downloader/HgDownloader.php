<?php declare(strict_types=1);











namespace Composer\Downloader;

use React\Promise\PromiseInterface;
use Composer\Package\PackageInterface;
use Composer\Util\ProcessExecutor;
use Composer\Util\Hg as HgUtils;




class HgDownloader extends VcsDownloader
{



protected function doDownload(PackageInterface $package, string $path, string $url, ?PackageInterface $prevPackage = null): PromiseInterface
{
if (null === HgUtils::getVersion($this->process)) {
throw new \RuntimeException('hg was not found in your PATH, skipping source download');
}

return \React\Promise\resolve(null);
}




protected function doInstall(PackageInterface $package, string $path, string $url): PromiseInterface
{
$hgUtils = new HgUtils($this->io, $this->config, $this->process);

$cloneCommand = static function (string $url) use ($path): string {
return sprintf('hg clone -- %s %s', ProcessExecutor::escape($url), ProcessExecutor::escape($path));
};

$hgUtils->runCommand($cloneCommand, $url, $path);

$ref = ProcessExecutor::escape($package->getSourceReference());
$command = sprintf('hg up -- %s', $ref);
if (0 !== $this->process->execute($command, $ignoredOutput, realpath($path))) {
throw new \RuntimeException('Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput());
}

return \React\Promise\resolve(null);
}




protected function doUpdate(PackageInterface $initial, PackageInterface $target, string $path, string $url): PromiseInterface
{
$hgUtils = new HgUtils($this->io, $this->config, $this->process);

$ref = $target->getSourceReference();
$this->io->writeError(" Updating to ".$target->getSourceReference());

if (!$this->hasMetadataRepository($path)) {
throw new \RuntimeException('The .hg directory is missing from '.$path.', see https://getcomposer.org/commit-deps for more information');
}

$command = static function ($url) use ($ref): string {
return sprintf('hg pull -- %s && hg up -- %s', ProcessExecutor::escape($url), ProcessExecutor::escape($ref));
};

$hgUtils->runCommand($command, $url, $path);

return \React\Promise\resolve(null);
}




public function getLocalChanges(PackageInterface $package, string $path): ?string
{
if (!is_dir($path.'/.hg')) {
return null;
}

$this->process->execute('hg st', $output, realpath($path));

$output = trim($output);

return strlen($output) > 0 ? $output : null;
}




protected function getCommitLogs(string $fromReference, string $toReference, string $path): string
{
$command = sprintf('hg log -r %s:%s --style compact', ProcessExecutor::escape($fromReference), ProcessExecutor::escape($toReference));

if (0 !== $this->process->execute($command, $output, realpath($path))) {
throw new \RuntimeException('Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput());
}

return $output;
}




protected function hasMetadataRepository(string $path): bool
{
return is_dir($path . '/.hg');
}
}
