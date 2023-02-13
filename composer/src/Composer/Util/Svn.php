<?php declare(strict_types=1);











namespace Composer\Util;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Pcre\Preg;





class Svn
{
private const MAX_QTY_AUTH_TRIES = 5;




protected $credentials;




protected $hasAuth;




protected $io;




protected $url;




protected $cacheCredentials = true;




protected $process;




protected $qtyAuthTries = 0;




protected $config;




private static $version;




public function __construct(string $url, IOInterface $io, Config $config, ?ProcessExecutor $process = null)
{
$this->url = $url;
$this->io = $io;
$this->config = $config;
$this->process = $process ?: new ProcessExecutor($io);
}

public static function cleanEnv(): void
{

Platform::clearEnv('DYLD_LIBRARY_PATH');
}













public function execute(string $command, string $url, ?string $cwd = null, ?string $path = null, bool $verbose = false): string
{

$this->config->prohibitUrlByConfig($url, $this->io);

return $this->executeWithAuthRetry($command, $cwd, $url, $path, $verbose);
}












public function executeLocal(string $command, string $path, ?string $cwd = null, bool $verbose = false): string
{

return $this->executeWithAuthRetry($command, $cwd, '', $path, $verbose);
}

private function executeWithAuthRetry(string $svnCommand, ?string $cwd, string $url, ?string $path, bool $verbose): ?string
{

$command = $this->getCommand($svnCommand, $url, $path);

$output = null;
$io = $this->io;
$handler = static function ($type, $buffer) use (&$output, $io, $verbose) {
if ($type !== 'out') {
return null;
}
if (strpos($buffer, 'Redirecting to URL ') === 0) {
return null;
}
$output .= $buffer;
if ($verbose) {
$io->writeError($buffer, false);
}
};
$status = $this->process->execute($command, $handler, $cwd);
if (0 === $status) {
return $output;
}

$errorOutput = $this->process->getErrorOutput();
$fullOutput = trim(implode("\n", [$output, $errorOutput]));


if (false === stripos($fullOutput, 'Could not authenticate to server:')
&& false === stripos($fullOutput, 'authorization failed')
&& false === stripos($fullOutput, 'svn: E170001:')
&& false === stripos($fullOutput, 'svn: E215004:')) {
throw new \RuntimeException($fullOutput);
}

if (!$this->hasAuth()) {
$this->doAuthDance();
}


if ($this->qtyAuthTries++ < self::MAX_QTY_AUTH_TRIES) {

return $this->executeWithAuthRetry($svnCommand, $cwd, $url, $path, $verbose);
}

throw new \RuntimeException(
'wrong credentials provided ('.$fullOutput.')'
);
}

public function setCacheCredentials(bool $cacheCredentials): void
{
$this->cacheCredentials = $cacheCredentials;
}







protected function doAuthDance(): Svn
{

if (!$this->io->isInteractive()) {
throw new \RuntimeException(
'can not ask for authentication in non interactive mode'
);
}

$this->io->writeError("The Subversion server ({$this->url}) requested credentials:");

$this->hasAuth = true;
$this->credentials = [
'username' => (string) $this->io->ask("Username: ", ''),
'password' => (string) $this->io->askAndHideAnswer("Password: "),
];

$this->cacheCredentials = $this->io->askConfirmation("Should Subversion cache these credentials? (yes/no) ");

return $this;
}








protected function getCommand(string $cmd, string $url, ?string $path = null): string
{
$cmd = sprintf(
'%s %s%s -- %s',
$cmd,
'--non-interactive ',
$this->getCredentialString(),
ProcessExecutor::escape($url)
);

if ($path) {
$cmd .= ' ' . ProcessExecutor::escape($path);
}

return $cmd;
}






protected function getCredentialString(): string
{
if (!$this->hasAuth()) {
return '';
}

return sprintf(
' %s--username %s --password %s ',
$this->getAuthCache(),
ProcessExecutor::escape($this->getUsername()),
ProcessExecutor::escape($this->getPassword())
);
}






protected function getPassword(): string
{
if ($this->credentials === null) {
throw new \LogicException("No svn auth detected.");
}

return $this->credentials['password'];
}






protected function getUsername(): string
{
if ($this->credentials === null) {
throw new \LogicException("No svn auth detected.");
}

return $this->credentials['username'];
}




protected function hasAuth(): bool
{
if (null !== $this->hasAuth) {
return $this->hasAuth;
}

if (false === $this->createAuthFromConfig()) {
$this->createAuthFromUrl();
}

return (bool) $this->hasAuth;
}




protected function getAuthCache(): string
{
return $this->cacheCredentials ? '' : '--no-auth-cache ';
}




private function createAuthFromConfig(): bool
{
if (!$this->config->has('http-basic')) {
return $this->hasAuth = false;
}

$authConfig = $this->config->get('http-basic');

$host = parse_url($this->url, PHP_URL_HOST);
if (isset($authConfig[$host])) {
$this->credentials = [
'username' => $authConfig[$host]['username'],
'password' => $authConfig[$host]['password'],
];

return $this->hasAuth = true;
}

return $this->hasAuth = false;
}




private function createAuthFromUrl(): bool
{
$uri = parse_url($this->url);
if (empty($uri['user'])) {
return $this->hasAuth = false;
}

$this->credentials = [
'username' => $uri['user'],
'password' => !empty($uri['pass']) ? $uri['pass'] : '',
];

return $this->hasAuth = true;
}




public function binaryVersion(): ?string
{
if (!self::$version) {
if (0 === $this->process->execute('svn --version', $output)) {
if (Preg::isMatch('{(\d+(?:\.\d+)+)}', $output, $match)) {
self::$version = $match[1];
}
}
}

return self::$version;
}
}
