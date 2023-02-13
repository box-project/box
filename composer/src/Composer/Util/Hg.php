<?php declare(strict_types=1);











namespace Composer\Util;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Pcre\Preg;




class Hg
{

private static $version = false;




private $io;




private $config;




private $process;

public function __construct(IOInterface $io, Config $config, ProcessExecutor $process)
{
$this->io = $io;
$this->config = $config;
$this->process = $process;
}

public function runCommand(callable $commandCallable, string $url, ?string $cwd): void
{
$this->config->prohibitUrlByConfig($url, $this->io);


$command = $commandCallable($url);

if (0 === $this->process->execute($command, $ignoredOutput, $cwd)) {
return;
}


if (Preg::isMatch('{^(https?)://((.+)(?:\:(.+))?@)?([^/]+)(/.*)?}mi', $url, $match) && $this->io->hasAuthentication((string) $match[5])) {
$auth = $this->io->getAuthentication((string) $match[5]);
$authenticatedUrl = $match[1] . '://' . rawurlencode($auth['username']) . ':' . rawurlencode($auth['password']) . '@' . $match[5] . $match[6];

$command = $commandCallable($authenticatedUrl);

if (0 === $this->process->execute($command, $ignoredOutput, $cwd)) {
return;
}

$error = $this->process->getErrorOutput();
} else {
$error = 'The given URL (' . $url . ') does not match the required format (http(s)://(username:password@)example.com/path-to-repository)';
}

$this->throwException('Failed to clone ' . $url . ', ' . "\n\n" . $error, $url);
}






private function throwException($message, string $url): void
{
if (null === self::getVersion($this->process)) {
throw new \RuntimeException(Url::sanitize('Failed to clone ' . $url . ', hg was not found, check that it is installed and in your PATH env.' . "\n\n" . $this->process->getErrorOutput()));
}

throw new \RuntimeException(Url::sanitize($message));
}






public static function getVersion(ProcessExecutor $process): ?string
{
if (false === self::$version) {
self::$version = null;
if (0 === $process->execute('hg --version', $output) && Preg::isMatch('/^.+? (\d+(?:\.\d+)+)(?:\+.*?)?\)?\r?\n/', $output, $matches)) {
self::$version = $matches[1];
}
}

return self::$version;
}
}
