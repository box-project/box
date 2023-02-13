<?php declare(strict_types=1);











namespace Composer\Downloader;

use React\Promise\PromiseInterface;
use Composer\Package\PackageInterface;
use Composer\Util\ProcessExecutor;







class XzDownloader extends ArchiveDownloader
{
protected function extract(PackageInterface $package, string $file, string $path): PromiseInterface
{
$command = 'tar -xJf ' . ProcessExecutor::escape($file) . ' -C ' . ProcessExecutor::escape($path);

if (0 === $this->process->execute($command, $ignoredOutput)) {
return \React\Promise\resolve(null);
}

$processError = 'Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput();

throw new \RuntimeException($processError);
}
}
