<?php declare(strict_types=1);











namespace Composer\Downloader;

use React\Promise\PromiseInterface;
use Composer\Package\PackageInterface;
use Composer\Util\Platform;
use Composer\Util\ProcessExecutor;






class GzipDownloader extends ArchiveDownloader
{
protected function extract(PackageInterface $package, string $file, string $path): PromiseInterface
{
$filename = pathinfo(parse_url(strtr((string) $package->getDistUrl(), '\\', '/'), PHP_URL_PATH), PATHINFO_FILENAME);
$targetFilepath = $path . DIRECTORY_SEPARATOR . $filename;


if (!Platform::isWindows()) {
$command = 'gzip -cd -- ' . ProcessExecutor::escape($file) . ' > ' . ProcessExecutor::escape($targetFilepath);

if (0 === $this->process->execute($command, $ignoredOutput)) {
return \React\Promise\resolve(null);
}

if (extension_loaded('zlib')) {

$this->extractUsingExt($file, $targetFilepath);

return \React\Promise\resolve(null);
}

$processError = 'Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput();
throw new \RuntimeException($processError);
}


$this->extractUsingExt($file, $targetFilepath);

return \React\Promise\resolve(null);
}

private function extractUsingExt(string $file, string $targetFilepath): void
{
$archiveFile = gzopen($file, 'rb');
$targetFile = fopen($targetFilepath, 'wb');
while ($string = gzread($archiveFile, 4096)) {
fwrite($targetFile, $string, Platform::strlen($string));
}
gzclose($archiveFile);
fclose($targetFile);
}
}
