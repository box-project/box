<?php declare(strict_types=1);











namespace Composer;

use Composer\Config\ConfigSourceInterface;
use Composer\Downloader\TransportException;
use Composer\IO\IOInterface;
use Composer\Pcre\Preg;
use Composer\Util\Platform;
use Composer\Util\ProcessExecutor;




class Config
{
public const SOURCE_DEFAULT = 'default';
public const SOURCE_COMMAND = 'command';
public const SOURCE_UNKNOWN = 'unknown';

public const RELATIVE_PATHS = 1;


public static $defaultConfig = [
'process-timeout' => 300,
'use-include-path' => false,
'allow-plugins' => [],
'use-parent-dir' => 'prompt',
'preferred-install' => 'dist',
'notify-on-install' => true,
'github-protocols' => ['https', 'ssh', 'git'],
'gitlab-protocol' => null,
'vendor-dir' => 'vendor',
'bin-dir' => '{$vendor-dir}/bin',
'cache-dir' => '{$home}/cache',
'data-dir' => '{$home}',
'cache-files-dir' => '{$cache-dir}/files',
'cache-repo-dir' => '{$cache-dir}/repo',
'cache-vcs-dir' => '{$cache-dir}/vcs',
'cache-ttl' => 15552000, 
'cache-files-ttl' => null, 
'cache-files-maxsize' => '300MiB',
'cache-read-only' => false,
'bin-compat' => 'auto',
'discard-changes' => false,
'autoloader-suffix' => null,
'sort-packages' => false,
'optimize-autoloader' => false,
'classmap-authoritative' => false,
'apcu-autoloader' => false,
'prepend-autoloader' => true,
'github-domains' => ['github.com'],
'bitbucket-expose-hostname' => true,
'disable-tls' => false,
'secure-http' => true,
'secure-svn-domains' => [],
'cafile' => null,
'capath' => null,
'github-expose-hostname' => true,
'gitlab-domains' => ['gitlab.com'],
'store-auths' => 'prompt',
'platform' => [],
'archive-format' => 'tar',
'archive-dir' => '.',
'htaccess-protect' => true,
'use-github-api' => true,
'lock' => true,
'platform-check' => 'php-only',
'bitbucket-oauth' => [],
'github-oauth' => [],
'gitlab-oauth' => [],
'gitlab-token' => [],
'http-basic' => [],
'bearer' => [],
];


public static $defaultRepositories = [
'packagist.org' => [
'type' => 'composer',
'url' => 'https://repo.packagist.org',
],
];


private $config;

private $baseDir;

private $repositories;

private $configSource;

private $authConfigSource;

private $localAuthConfigSource = null;

private $useEnvironment;

private $warnedHosts = [];

private $sslVerifyWarnedHosts = [];

private $sourceOfConfigValue = [];





public function __construct(bool $useEnvironment = true, ?string $baseDir = null)
{

$this->config = static::$defaultConfig;

$this->repositories = static::$defaultRepositories;
$this->useEnvironment = (bool) $useEnvironment;
$this->baseDir = is_string($baseDir) && '' !== $baseDir ? $baseDir : null;

foreach ($this->config as $configKey => $configValue) {
$this->setSourceOfConfigValue($configValue, $configKey, self::SOURCE_DEFAULT);
}

foreach ($this->repositories as $configKey => $configValue) {
$this->setSourceOfConfigValue($configValue, 'repositories.' . $configKey, self::SOURCE_DEFAULT);
}
}

public function setConfigSource(ConfigSourceInterface $source): void
{
$this->configSource = $source;
}

public function getConfigSource(): ConfigSourceInterface
{
return $this->configSource;
}

public function setAuthConfigSource(ConfigSourceInterface $source): void
{
$this->authConfigSource = $source;
}

public function getAuthConfigSource(): ConfigSourceInterface
{
return $this->authConfigSource;
}

public function setLocalAuthConfigSource(ConfigSourceInterface $source): void
{
$this->localAuthConfigSource = $source;
}

public function getLocalAuthConfigSource(): ?ConfigSourceInterface
{
return $this->localAuthConfigSource;
}






public function merge(array $config, string $source = self::SOURCE_UNKNOWN): void
{

if (!empty($config['config']) && is_array($config['config'])) {
foreach ($config['config'] as $key => $val) {
if (in_array($key, ['bitbucket-oauth', 'github-oauth', 'gitlab-oauth', 'gitlab-token', 'http-basic', 'bearer'], true) && isset($this->config[$key])) {
$this->config[$key] = array_merge($this->config[$key], $val);
$this->setSourceOfConfigValue($val, $key, $source);
} elseif (in_array($key, ['allow-plugins'], true) && isset($this->config[$key]) && is_array($this->config[$key]) && is_array($val)) {


$this->config[$key] = array_merge($val, $this->config[$key], $val);
$this->setSourceOfConfigValue($val, $key, $source);
} elseif (in_array($key, ['gitlab-domains', 'github-domains'], true) && isset($this->config[$key])) {
$this->config[$key] = array_unique(array_merge($this->config[$key], $val));
$this->setSourceOfConfigValue($val, $key, $source);
} elseif ('preferred-install' === $key && isset($this->config[$key])) {
if (is_array($val) || is_array($this->config[$key])) {
if (is_string($val)) {
$val = ['*' => $val];
}
if (is_string($this->config[$key])) {
$this->config[$key] = ['*' => $this->config[$key]];
$this->sourceOfConfigValue[$key . '*'] = $source;
}
$this->config[$key] = array_merge($this->config[$key], $val);
$this->setSourceOfConfigValue($val, $key, $source);

if (isset($this->config[$key]['*'])) {
$wildcard = $this->config[$key]['*'];
unset($this->config[$key]['*']);
$this->config[$key]['*'] = $wildcard;
}
} else {
$this->config[$key] = $val;
$this->setSourceOfConfigValue($val, $key, $source);
}
} else {
$this->config[$key] = $val;
$this->setSourceOfConfigValue($val, $key, $source);
}
}
}

if (!empty($config['repositories']) && is_array($config['repositories'])) {
$this->repositories = array_reverse($this->repositories, true);
$newRepos = array_reverse($config['repositories'], true);
foreach ($newRepos as $name => $repository) {

if (false === $repository) {
$this->disableRepoByName((string) $name);
continue;
}


if (is_array($repository) && 1 === count($repository) && false === current($repository)) {
$this->disableRepoByName((string) key($repository));
continue;
}


if (isset($repository['type'], $repository['url']) && $repository['type'] === 'composer' && Preg::isMatch('{^https?://(?:[a-z0-9-.]+\.)?packagist.org(/|$)}', $repository['url'])) {
$this->disableRepoByName('packagist.org');
}


if (is_int($name)) {
$this->repositories[] = $repository;
$this->setSourceOfConfigValue($repository, 'repositories.' . array_search($repository, $this->repositories, true), $source);
} else {
if ($name === 'packagist') { 
$this->repositories[$name . '.org'] = $repository;
$this->setSourceOfConfigValue($repository, 'repositories.' . $name . '.org', $source);
} else {
$this->repositories[$name] = $repository;
$this->setSourceOfConfigValue($repository, 'repositories.' . $name, $source);
}
}
}
$this->repositories = array_reverse($this->repositories, true);
}
}




public function getRepositories(): array
{
return $this->repositories;
}









public function get(string $key, int $flags = 0)
{
switch ($key) {

case 'vendor-dir':
case 'bin-dir':
case 'process-timeout':
case 'data-dir':
case 'cache-dir':
case 'cache-files-dir':
case 'cache-repo-dir':
case 'cache-vcs-dir':
case 'cafile':
case 'capath':

$env = 'COMPOSER_' . strtoupper(strtr($key, '-', '_'));

$val = $this->getComposerEnv($env);
if ($val !== false) {
$this->setSourceOfConfigValue($val, $key, $env);
}

if ($key === 'process-timeout') {
return max(0, false !== $val ? (int) $val : $this->config[$key]);
}

$val = rtrim((string) $this->process(false !== $val ? $val : $this->config[$key], $flags), '/\\');
$val = Platform::expandPath($val);

if (substr($key, -4) !== '-dir') {
return $val;
}

return (($flags & self::RELATIVE_PATHS) === self::RELATIVE_PATHS) ? $val : $this->realpath($val);


case 'cache-read-only':
case 'htaccess-protect':

$env = 'COMPOSER_' . strtoupper(strtr($key, '-', '_'));

$val = $this->getComposerEnv($env);
if (false === $val) {
$val = $this->config[$key];
} else {
$this->setSourceOfConfigValue($val, $key, $env);
}

return $val !== 'false' && (bool) $val;


case 'disable-tls':
case 'secure-http':
case 'use-github-api':
case 'lock':

if ($key === 'secure-http' && $this->get('disable-tls') === true) {
return false;
}

return $this->config[$key] !== 'false' && (bool) $this->config[$key];


case 'cache-ttl':
return max(0, (int) $this->config[$key]);


case 'cache-files-maxsize':
if (!Preg::isMatch('/^\s*([0-9.]+)\s*(?:([kmg])(?:i?b)?)?\s*$/i', (string) $this->config[$key], $matches)) {
throw new \RuntimeException(
"Could not parse the value of '$key': {$this->config[$key]}"
);
}
$size = (float) $matches[1];
if (isset($matches[2])) {
switch (strtolower($matches[2])) {
case 'g':
$size *= 1024;


case 'm':
$size *= 1024;


case 'k':
$size *= 1024;
break;
}
}

return max(0, (int) $size);


case 'cache-files-ttl':
if (isset($this->config[$key])) {
return max(0, (int) $this->config[$key]);
}

return $this->get('cache-ttl');

case 'home':
return rtrim($this->process(Platform::expandPath($this->config[$key]), $flags), '/\\');

case 'bin-compat':
$value = $this->getComposerEnv('COMPOSER_BIN_COMPAT') ?: $this->config[$key];

if (!in_array($value, ['auto', 'full', 'proxy', 'symlink'])) {
throw new \RuntimeException(
"Invalid value for 'bin-compat': {$value}. Expected auto, full or proxy"
);
}

if ($value === 'symlink') {
trigger_error('config.bin-compat "symlink" is deprecated since Composer 2.2, use auto, full (for Windows compatibility) or proxy instead.', E_USER_DEPRECATED);
}

return $value;

case 'discard-changes':
$env = $this->getComposerEnv('COMPOSER_DISCARD_CHANGES');
if ($env !== false) {
if (!in_array($env, ['stash', 'true', 'false', '1', '0'], true)) {
throw new \RuntimeException(
"Invalid value for COMPOSER_DISCARD_CHANGES: {$env}. Expected 1, 0, true, false or stash"
);
}
if ('stash' === $env) {
return 'stash';
}


return $env !== 'false' && (bool) $env;
}

if (!in_array($this->config[$key], [true, false, 'stash'], true)) {
throw new \RuntimeException(
"Invalid value for 'discard-changes': {$this->config[$key]}. Expected true, false or stash"
);
}

return $this->config[$key];

case 'github-protocols':
$protos = $this->config['github-protocols'];
if ($this->config['secure-http'] && false !== ($index = array_search('git', $protos))) {
unset($protos[$index]);
}
if (reset($protos) === 'http') {
throw new \RuntimeException('The http protocol for github is not available anymore, update your config\'s github-protocols to use "https", "git" or "ssh"');
}

return $protos;

case 'autoloader-suffix':
if ($this->config[$key] === '') { 
return null;
}

return $this->process($this->config[$key], $flags);

default:
if (!isset($this->config[$key])) {
return null;
}

return $this->process($this->config[$key], $flags);
}
}




public function all(int $flags = 0): array
{
$all = [
'repositories' => $this->getRepositories(),
];
foreach (array_keys($this->config) as $key) {
$all['config'][$key] = $this->get($key, $flags);
}

return $all;
}

public function getSourceOfValue(string $key): string
{
$this->get($key);

return $this->sourceOfConfigValue[$key] ?? self::SOURCE_UNKNOWN;
}




private function setSourceOfConfigValue($configValue, string $path, string $source): void
{
$this->sourceOfConfigValue[$path] = $source;

if (is_array($configValue)) {
foreach ($configValue as $key => $value) {
$this->setSourceOfConfigValue($value, $path . '.' . $key, $source);
}
}
}




public function raw(): array
{
return [
'repositories' => $this->getRepositories(),
'config' => $this->config,
];
}




public function has(string $key): bool
{
return array_key_exists($key, $this->config);
}









private function process($value, int $flags)
{
if (!is_string($value)) {
return $value;
}

return Preg::replaceCallback('#\{\$(.+)\}#', function ($match) use ($flags) {
assert(is_string($match[1]));
return $this->get($match[1], $flags);
}, $value);
}






private function realpath(string $path): string
{
if (Preg::isMatch('{^(?:/|[a-z]:|[a-z0-9.]+://|\\\\\\\\)}i', $path)) {
return $path;
}

return $this->baseDir ? $this->baseDir . '/' . $path : $path;
}









private function getComposerEnv(string $var)
{
if ($this->useEnvironment) {
return Platform::getEnv($var);
}

return false;
}

private function disableRepoByName(string $name): void
{
if (isset($this->repositories[$name])) {
unset($this->repositories[$name]);
} elseif ($name === 'packagist') { 
unset($this->repositories['packagist.org']);
}
}







public function prohibitUrlByConfig(string $url, ?IOInterface $io = null, array $repoOptions = []): void
{

if (false === filter_var($url, FILTER_VALIDATE_URL)) {
return;
}


$scheme = parse_url($url, PHP_URL_SCHEME);
$hostname = parse_url($url, PHP_URL_HOST);
if (in_array($scheme, ['http', 'git', 'ftp', 'svn'])) {
if ($this->get('secure-http')) {
if ($scheme === 'svn') {
if (in_array($hostname, $this->get('secure-svn-domains'), true)) {
return;
}

throw new TransportException("Your configuration does not allow connections to $url. See https://getcomposer.org/doc/06-config.md#secure-svn-domains for details.");
}

throw new TransportException("Your configuration does not allow connections to $url. See https://getcomposer.org/doc/06-config.md#secure-http for details.");
}
if ($io !== null) {
if (is_string($hostname)) {
if (!isset($this->warnedHosts[$hostname])) {
$io->writeError("<warning>Warning: Accessing $hostname over $scheme which is an insecure protocol.</warning>");
}
$this->warnedHosts[$hostname] = true;
}
}
}

if ($io !== null && is_string($hostname) && !isset($this->sslVerifyWarnedHosts[$hostname])) {
$warning = null;
if (isset($repoOptions['ssl']['verify_peer']) && !(bool) $repoOptions['ssl']['verify_peer']) {
$warning = 'verify_peer';
}

if (isset($repoOptions['ssl']['verify_peer_name']) && !(bool) $repoOptions['ssl']['verify_peer_name']) {
$warning = $warning === null ? 'verify_peer_name' : $warning . ' and verify_peer_name';
}

if ($warning !== null) {
$io->writeError("<warning>Warning: Accessing $hostname with $warning disabled.</warning>");
$this->sslVerifyWarnedHosts[$hostname] = true;
}
}
}











public static function disableProcessTimeout(): void
{

ProcessExecutor::setTimeout(0);
}
}
