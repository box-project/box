<?php declare(strict_types=1);











namespace Composer\Util;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Pcre\Preg;




class Git
{

private static $version = false;


protected $io;

protected $config;

protected $process;

protected $filesystem;

public function __construct(IOInterface $io, Config $config, ProcessExecutor $process, Filesystem $fs)
{
$this->io = $io;
$this->config = $config;
$this->process = $process;
$this->filesystem = $fs;
}





public function runCommand(callable $commandCallable, string $url, ?string $cwd, bool $initialClone = false, &$commandOutput = null): void
{

$this->config->prohibitUrlByConfig($url, $this->io);

if ($initialClone) {
$origCwd = $cwd;
$cwd = null;
}

if (Preg::isMatch('{^ssh://[^@]+@[^:]+:[^0-9]+}', $url)) {
throw new \InvalidArgumentException('The source URL ' . $url . ' is invalid, ssh URLs should have a port number after ":".' . "\n" . 'Use ssh://git@example.com:22/path or just git@example.com:path if you do not want to provide a password or custom port.');
}

if (!$initialClone) {

$this->process->execute('git remote -v', $output, $cwd);
if (Preg::isMatchStrictGroups('{^(?:composer|origin)\s+https?://(.+):(.+)@([^/]+)}im', $output, $match) && !$this->io->hasAuthentication($match[3])) {
$this->io->setAuthentication($match[3], rawurldecode($match[1]), rawurldecode($match[2]));
}
}

$protocols = $this->config->get('github-protocols');

if (Preg::isMatchStrictGroups('{^(?:https?|git)://' . self::getGitHubDomainsRegex($this->config) . '/(.*)}', $url, $match)) {
$messages = [];
foreach ($protocols as $protocol) {
if ('ssh' === $protocol) {
$protoUrl = "git@" . $match[1] . ":" . $match[2];
} else {
$protoUrl = $protocol . "://" . $match[1] . "/" . $match[2];
}

if (0 === $this->process->execute($commandCallable($protoUrl), $commandOutput, $cwd)) {
return;
}
$messages[] = '- ' . $protoUrl . "\n" . Preg::replace('#^#m', '  ', $this->process->getErrorOutput());

if ($initialClone && isset($origCwd)) {
$this->filesystem->removeDirectory($origCwd);
}
}


if (!$this->io->hasAuthentication($match[1]) && !$this->io->isInteractive()) {
$this->throwException('Failed to clone ' . $url . ' via ' . implode(', ', $protocols) . ' protocols, aborting.' . "\n\n" . implode("\n", $messages), $url);
}
}


$bypassSshForGitHub = Preg::isMatch('{^git@' . self::getGitHubDomainsRegex($this->config) . ':(.+?)\.git$}i', $url) && !in_array('ssh', $protocols, true);

$command = $commandCallable($url);

$auth = null;
$credentials = [];
if ($bypassSshForGitHub || 0 !== $this->process->execute($command, $commandOutput, $cwd)) {
$errorMsg = $this->process->getErrorOutput();

if (Preg::isMatchStrictGroups('{^git@' . self::getGitHubDomainsRegex($this->config) . ':(.+?)\.git$}i', $url, $match)
|| Preg::isMatchStrictGroups('{^https?://' . self::getGitHubDomainsRegex($this->config) . '/(.*?)(?:\.git)?$}i', $url, $match)
) {
if (!$this->io->hasAuthentication($match[1])) {
$gitHubUtil = new GitHub($this->io, $this->config, $this->process);
$message = 'Cloning failed using an ssh key for authentication, enter your GitHub credentials to access private repos';

if (!$gitHubUtil->authorizeOAuth($match[1]) && $this->io->isInteractive()) {
$gitHubUtil->authorizeOAuthInteractively($match[1], $message);
}
}

if ($this->io->hasAuthentication($match[1])) {
$auth = $this->io->getAuthentication($match[1]);
$authUrl = 'https://' . rawurlencode($auth['username']) . ':' . rawurlencode($auth['password']) . '@' . $match[1] . '/' . $match[2] . '.git';
$command = $commandCallable($authUrl);
if (0 === $this->process->execute($command, $commandOutput, $cwd)) {
return;
}

$credentials = [rawurlencode($auth['username']), rawurlencode($auth['password'])];
$errorMsg = $this->process->getErrorOutput();
}
} elseif (Preg::isMatchStrictGroups('{^https://(bitbucket\.org)/(.*?)(?:\.git)?$}i', $url, $match)) { 
$bitbucketUtil = new Bitbucket($this->io, $this->config, $this->process);

if (!$this->io->hasAuthentication($match[1])) {
$message = 'Enter your Bitbucket credentials to access private repos';

if (!$bitbucketUtil->authorizeOAuth($match[1]) && $this->io->isInteractive()) {
$bitbucketUtil->authorizeOAuthInteractively($match[1], $message);
$accessToken = $bitbucketUtil->getToken();
$this->io->setAuthentication($match[1], 'x-token-auth', $accessToken);
}
} else { 
$auth = $this->io->getAuthentication($match[1]);


if ($auth['username'] !== 'x-token-auth') {
$accessToken = $bitbucketUtil->requestToken($match[1], $auth['username'], $auth['password']);
if (!empty($accessToken)) {
$this->io->setAuthentication($match[1], 'x-token-auth', $accessToken);
}
}
}

if ($this->io->hasAuthentication($match[1])) {
$auth = $this->io->getAuthentication($match[1]);
$authUrl = 'https://' . rawurlencode($auth['username']) . ':' . rawurlencode($auth['password']) . '@' . $match[1] . '/' . $match[2] . '.git';

$command = $commandCallable($authUrl);
if (0 === $this->process->execute($command, $commandOutput, $cwd)) {
return;
}

$credentials = [rawurlencode($auth['username']), rawurlencode($auth['password'])];
$errorMsg = $this->process->getErrorOutput();
} else { 
$sshUrl = 'git@bitbucket.org:' . $match[2] . '.git';
$this->io->writeError('    No bitbucket authentication configured. Falling back to ssh.');
$command = $commandCallable($sshUrl);
if (0 === $this->process->execute($command, $commandOutput, $cwd)) {
return;
}

$errorMsg = $this->process->getErrorOutput();
}
} elseif (
Preg::isMatchStrictGroups('{^(git)@' . self::getGitLabDomainsRegex($this->config) . ':(.+?\.git)$}i', $url, $match)
|| Preg::isMatchStrictGroups('{^(https?)://' . self::getGitLabDomainsRegex($this->config) . '/(.*)}i', $url, $match)
) {
if ($match[1] === 'git') {
$match[1] = 'https';
}

if (!$this->io->hasAuthentication($match[2])) {
$gitLabUtil = new GitLab($this->io, $this->config, $this->process);
$message = 'Cloning failed, enter your GitLab credentials to access private repos';

if (!$gitLabUtil->authorizeOAuth($match[2]) && $this->io->isInteractive()) {
$gitLabUtil->authorizeOAuthInteractively($match[1], $match[2], $message);
}
}

if ($this->io->hasAuthentication($match[2])) {
$auth = $this->io->getAuthentication($match[2]);
if ($auth['password'] === 'private-token' || $auth['password'] === 'oauth2' || $auth['password'] === 'gitlab-ci-token') {
$authUrl = $match[1] . '://' . rawurlencode($auth['password']) . ':' . rawurlencode($auth['username']) . '@' . $match[2] . '/' . $match[3]; 
} else {
$authUrl = $match[1] . '://' . rawurlencode($auth['username']) . ':' . rawurlencode($auth['password']) . '@' . $match[2] . '/' . $match[3];
}

$command = $commandCallable($authUrl);
if (0 === $this->process->execute($command, $commandOutput, $cwd)) {
return;
}

$credentials = [rawurlencode($auth['username']), rawurlencode($auth['password'])];
$errorMsg = $this->process->getErrorOutput();
}
} elseif ($this->isAuthenticationFailure($url, $match)) { 
if (strpos($match[2], '@')) {
[$authParts, $match[2]] = explode('@', $match[2], 2);
}

$storeAuth = false;
if ($this->io->hasAuthentication($match[2])) {
$auth = $this->io->getAuthentication($match[2]);
} elseif ($this->io->isInteractive()) {
$defaultUsername = null;
if (isset($authParts) && $authParts) {
if (false !== strpos($authParts, ':')) {
[$defaultUsername, ] = explode(':', $authParts, 2);
} else {
$defaultUsername = $authParts;
}
}

$this->io->writeError('    Authentication required (<info>' . $match[2] . '</info>):');
$auth = [
'username' => $this->io->ask('      Username: ', $defaultUsername),
'password' => $this->io->askAndHideAnswer('      Password: '),
];
$storeAuth = $this->config->get('store-auths');
}

if (null !== $auth) {
$authUrl = $match[1] . rawurlencode($auth['username']) . ':' . rawurlencode($auth['password']) . '@' . $match[2] . $match[3];

$command = $commandCallable($authUrl);
if (0 === $this->process->execute($command, $commandOutput, $cwd)) {
$this->io->setAuthentication($match[2], $auth['username'], $auth['password']);
$authHelper = new AuthHelper($this->io, $this->config);
$authHelper->storeAuth($match[2], $storeAuth);

return;
}

$credentials = [rawurlencode($auth['username']), rawurlencode($auth['password'])];
$errorMsg = $this->process->getErrorOutput();
}
}

if ($initialClone && isset($origCwd)) {
$this->filesystem->removeDirectory($origCwd);
}

if (count($credentials) > 0) {
$command = $this->maskCredentials($command, $credentials);
$errorMsg = $this->maskCredentials($errorMsg, $credentials);
}
$this->throwException('Failed to execute ' . $command . "\n\n" . $errorMsg, $url);
}
}

public function syncMirror(string $url, string $dir): bool
{
if (Platform::getEnv('COMPOSER_DISABLE_NETWORK') && Platform::getEnv('COMPOSER_DISABLE_NETWORK') !== 'prime') {
$this->io->writeError('<warning>Aborting git mirror sync of '.$url.' as network is disabled</warning>');

return false;
}


if (is_dir($dir) && 0 === $this->process->execute('git rev-parse --git-dir', $output, $dir) && trim($output) === '.') {
try {
$commandCallable = static function ($url): string {
$sanitizedUrl = Preg::replace('{://([^@]+?):(.+?)@}', '://', $url);

return sprintf('git remote set-url origin -- %s && git remote update --prune origin && git remote set-url origin -- %s && git gc --auto', ProcessExecutor::escape($url), ProcessExecutor::escape($sanitizedUrl));
};
$this->runCommand($commandCallable, $url, $dir);
} catch (\Exception $e) {
$this->io->writeError('<error>Sync mirror failed: ' . $e->getMessage() . '</error>', true, IOInterface::DEBUG);

return false;
}

return true;
}


$this->filesystem->removeDirectory($dir);

$commandCallable = static function ($url) use ($dir): string {
return sprintf('git clone --mirror -- %s %s', ProcessExecutor::escape($url), ProcessExecutor::escape($dir));
};

$this->runCommand($commandCallable, $url, $dir, true);

return true;
}

public function fetchRefOrSyncMirror(string $url, string $dir, string $ref, string $prettyVersion = null): bool
{
if ($this->checkRefIsInMirror($dir, $ref)) {
if (Preg::isMatch('{^[a-f0-9]{40}$}', $ref) && $prettyVersion !== null) {
$branch = Preg::replace('{(?:^dev-|(?:\.x)?-dev$)}i', '', $prettyVersion);
$branches = null;
$tags = null;
if (0 === $this->process->execute('git branch', $output, $dir)) {
$branches = $output;
}
if (0 === $this->process->execute('git tag', $output, $dir)) {
$tags = $output;
}





if (null !== $branches && !Preg::isMatch('{^[\s*]*v?'.preg_quote($branch).'$}m', $branches)
&& null !== $tags && !Preg::isMatch('{^[\s*]*'.preg_quote($branch).'$}m', $tags)
) {
$this->syncMirror($url, $dir);
}
}

return true;
}

if ($this->syncMirror($url, $dir)) {
return $this->checkRefIsInMirror($dir, $ref);
}

return false;
}

public static function getNoShowSignatureFlag(ProcessExecutor $process): string
{
$gitVersion = self::getVersion($process);
if ($gitVersion && version_compare($gitVersion, '2.10.0-rc0', '>=')) {
return ' --no-show-signature';
}

return '';
}

private function checkRefIsInMirror(string $dir, string $ref): bool
{
if (is_dir($dir) && 0 === $this->process->execute('git rev-parse --git-dir', $output, $dir) && trim($output) === '.') {
$escapedRef = ProcessExecutor::escape($ref.'^{commit}');
$exitCode = $this->process->execute(sprintf('git rev-parse --quiet --verify %s', $escapedRef), $ignoredOutput, $dir);
if ($exitCode === 0) {
return true;
}
}

return false;
}




private function isAuthenticationFailure(string $url, array &$match): bool
{
if (!Preg::isMatch('{^(https?://)([^/]+)(.*)$}i', $url, $match)) {
return false;
}

$authFailures = [
'fatal: Authentication failed',
'remote error: Invalid username or password.',
'error: 401 Unauthorized',
'fatal: unable to access',
'fatal: could not read Username',
];

$errorOutput = $this->process->getErrorOutput();
foreach ($authFailures as $authFailure) {
if (strpos($errorOutput, $authFailure) !== false) {
return true;
}
}

return false;
}

public function getMirrorDefaultBranch(string $url, string $dir, bool $isLocalPathRepository): ?string
{
if ((bool) Platform::getEnv('COMPOSER_DISABLE_NETWORK')) {
return null;
}

try {
if ($isLocalPathRepository) {
$this->process->execute('git remote show origin', $output, $dir);
} else {
$commandCallable = static function ($url): string {
$sanitizedUrl = Preg::replace('{://([^@]+?):(.+?)@}', '://', $url);

return sprintf('git remote set-url origin -- %s && git remote show origin && git remote set-url origin -- %s', ProcessExecutor::escape($url), ProcessExecutor::escape($sanitizedUrl));
};

$this->runCommand($commandCallable, $url, $dir, false, $output);
}

$lines = $this->process->splitLines($output);
foreach ($lines as $line) {
if (Preg::match('{^\s*HEAD branch:\s(.+)\s*$}m', $line, $matches) > 0) {
return $matches[1];
}
}
} catch (\Exception $e) {
$this->io->writeError('<error>Failed to fetch root identifier from remote: ' . $e->getMessage() . '</error>', true, IOInterface::DEBUG);
}

return null;
}

public static function cleanEnv(): void
{

if (Platform::getEnv('GIT_ASKPASS') !== 'echo') {
Platform::putEnv('GIT_ASKPASS', 'echo');
}


if (Platform::getEnv('GIT_DIR')) {
Platform::clearEnv('GIT_DIR');
}
if (Platform::getEnv('GIT_WORK_TREE')) {
Platform::clearEnv('GIT_WORK_TREE');
}


if (Platform::getEnv('LANGUAGE') !== 'C') {
Platform::putEnv('LANGUAGE', 'C');
}


Platform::clearEnv('DYLD_LIBRARY_PATH');
}




public static function getGitHubDomainsRegex(Config $config): string
{
return '(' . implode('|', array_map('preg_quote', $config->get('github-domains'))) . ')';
}




public static function getGitLabDomainsRegex(Config $config): string
{
return '(' . implode('|', array_map('preg_quote', $config->get('gitlab-domains'))) . ')';
}






private function throwException($message, string $url): void
{

clearstatcache();

if (0 !== $this->process->execute('git --version', $ignoredOutput)) {
throw new \RuntimeException(Url::sanitize('Failed to clone ' . $url . ', git was not found, check that it is installed and in your PATH env.' . "\n\n" . $this->process->getErrorOutput()));
}

throw new \RuntimeException(Url::sanitize($message));
}






public static function getVersion(ProcessExecutor $process): ?string
{
if (false === self::$version) {
self::$version = null;
if (0 === $process->execute('git --version', $output) && Preg::isMatch('/^git version (\d+(?:\.\d+)+)/m', $output, $matches)) {
self::$version = $matches[1];
}
}

return self::$version;
}




private function maskCredentials(string $error, array $credentials): string
{
$maskedCredentials = [];

foreach ($credentials as $credential) {
if (in_array($credential, ['private-token', 'x-token-auth', 'oauth2', 'gitlab-ci-token', 'x-oauth-basic'])) {
$maskedCredentials[] = $credential;
} elseif (strlen($credential) > 6) {
$maskedCredentials[] = substr($credential, 0, 3) . '...' . substr($credential, -3);
} elseif (strlen($credential) > 3) {
$maskedCredentials[] = substr($credential, 0, 3) . '...';
} else {
$maskedCredentials[] = 'XXX';
}
}

return str_replace($credentials, $maskedCredentials, $error);
}
}
