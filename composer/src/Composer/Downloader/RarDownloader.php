<?php declare(strict_types=1);











namespace Composer\Downloader;

use React\Promise\PromiseInterface;
use Composer\Util\IniHelper;
use Composer\Util\Platform;
use Composer\Util\ProcessExecutor;
use Composer\Package\PackageInterface;
use RarArchive;








class RarDownloader extends ArchiveDownloader
{
protected function extract(PackageInterface $package, string $file, string $path): PromiseInterface
{
$processError = null;


if (!Platform::isWindows()) {
$command = 'unrar x -- ' . ProcessExecutor::escape($file) . ' ' . ProcessExecutor::escape($path) . ' >/dev/null && chmod -R u+w ' . ProcessExecutor::escape($path);

if (0 === $this->process->execute($command, $ignoredOutput)) {
return \React\Promise\resolve(null);
}

$processError = 'Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput();
}

if (!class_exists('RarArchive')) {

$iniMessage = IniHelper::getMessage();

$error = "Could not decompress the archive, enable the PHP rar extension or install unrar.\n"
. $iniMessage . "\n" . $processError;

if (!Platform::isWindows()) {
$error = "Could not decompress the archive, enable the PHP rar extension.\n" . $iniMessage;
}

throw new \RuntimeException($error);
}

$rarArchive = RarArchive::open($file);

if (false === $rarArchive) {
throw new \UnexpectedValueException('Could not open RAR archive: ' . $file);
}

$entries = $rarArchive->getEntries();

if (false === $entries) {
throw new \RuntimeException('Could not retrieve RAR archive entries');
}

foreach ($entries as $entry) {
if (false === $entry->extract($path)) {
throw new \RuntimeException('Could not extract entry');
}
}

$rarArchive->close();

return \React\Promise\resolve(null);
}
}
