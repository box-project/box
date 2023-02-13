<?php declare(strict_types=1);











namespace Composer\Repository\Vcs;

use Composer\Cache;
use Composer\Config;
use Composer\Json\JsonFile;
use Composer\Pcre\Preg;
use Composer\Util\ProcessExecutor;
use Composer\Util\Filesystem;
use Composer\Util\Url;
use Composer\Util\Svn as SvnUtil;
use Composer\IO\IOInterface;
use Composer\Downloader\TransportException;





class SvnDriver extends VcsDriver
{

protected $baseUrl;

protected $tags;

protected $branches;

protected $rootIdentifier;


protected $trunkPath = 'trunk';

protected $branchesPath = 'branches';

protected $tagsPath = 'tags';

protected $packagePath = '';

protected $cacheCredentials = true;




private $util;




public function initialize(): void
{
$this->url = $this->baseUrl = rtrim(self::normalizeUrl($this->url), '/');

SvnUtil::cleanEnv();

if (isset($this->repoConfig['trunk-path'])) {
$this->trunkPath = $this->repoConfig['trunk-path'];
}
if (isset($this->repoConfig['branches-path'])) {
$this->branchesPath = $this->repoConfig['branches-path'];
}
if (isset($this->repoConfig['tags-path'])) {
$this->tagsPath = $this->repoConfig['tags-path'];
}
if (array_key_exists('svn-cache-credentials', $this->repoConfig)) {
$this->cacheCredentials = (bool) $this->repoConfig['svn-cache-credentials'];
}
if (isset($this->repoConfig['package-path'])) {
$this->packagePath = '/' . trim($this->repoConfig['package-path'], '/');
}

if (false !== ($pos = strrpos($this->url, '/' . $this->trunkPath))) {
$this->baseUrl = substr($this->url, 0, $pos);
}

$this->cache = new Cache($this->io, $this->config->get('cache-repo-dir').'/'.Preg::replace('{[^a-z0-9.]}i', '-', Url::sanitize($this->baseUrl)));
$this->cache->setReadOnly($this->config->get('cache-read-only'));

$this->getBranches();
$this->getTags();
}




public function getRootIdentifier(): string
{
return $this->rootIdentifier ?: $this->trunkPath;
}




public function getUrl(): string
{
return $this->url;
}




public function getSource(string $identifier): array
{
return ['type' => 'svn', 'url' => $this->baseUrl, 'reference' => $identifier];
}




public function getDist(string $identifier): ?array
{
return null;
}




protected function shouldCache(string $identifier): bool
{
return $this->cache && Preg::isMatch('{@\d+$}', $identifier);
}




public function getComposerInformation(string $identifier): ?array
{
if (!isset($this->infoCache[$identifier])) {
if ($this->shouldCache($identifier) && $res = $this->cache->read($identifier.'.json')) {


if ($res === '""') {
$res = 'null';
$this->cache->write($identifier.'.json', json_encode(null));
}

return $this->infoCache[$identifier] = JsonFile::parseJson($res);
}

try {
$composer = $this->getBaseComposerInformation($identifier);
} catch (TransportException $e) {
$message = $e->getMessage();
if (stripos($message, 'path not found') === false && stripos($message, 'svn: warning: W160013') === false) {
throw $e;
}

$composer = null;
}

if ($this->shouldCache($identifier)) {
$this->cache->write($identifier.'.json', json_encode($composer));
}

$this->infoCache[$identifier] = $composer;
}


if (!is_array($this->infoCache[$identifier])) {
return null;
}

return $this->infoCache[$identifier];
}

public function getFileContent(string $file, string $identifier): ?string
{
$identifier = '/' . trim($identifier, '/') . '/';

Preg::match('{^(.+?)(@\d+)?/$}', $identifier, $match);
if (!empty($match[2])) {
$path = $match[1];
$rev = $match[2];
} else {
$path = $identifier;
$rev = '';
}

try {
$resource = $path.$file;
$output = $this->execute('svn cat', $this->baseUrl . $resource . $rev);
if (!trim($output)) {
return null;
}
} catch (\RuntimeException $e) {
throw new TransportException($e->getMessage());
}

return $output;
}




public function getChangeDate(string $identifier): ?\DateTimeImmutable
{
$identifier = '/' . trim($identifier, '/') . '/';

Preg::match('{^(.+?)(@\d+)?/$}', $identifier, $match);
if (null !== $match[2] && null !== $match[1]) {
$path = $match[1];
$rev = $match[2];
} else {
$path = $identifier;
$rev = '';
}

$output = $this->execute('svn info', $this->baseUrl . $path . $rev);
foreach ($this->process->splitLines($output) as $line) {
if ($line && Preg::isMatchStrictGroups('{^Last Changed Date: ([^(]+)}', $line, $match)) {
return new \DateTimeImmutable($match[1], new \DateTimeZone('UTC'));
}
}

return null;
}




public function getTags(): array
{
if (null === $this->tags) {
$tags = [];

if ($this->tagsPath !== false) {
$output = $this->execute('svn ls --verbose', $this->baseUrl . '/' . $this->tagsPath);
if ($output) {
foreach ($this->process->splitLines($output) as $line) {
$line = trim($line);
if ($line && Preg::isMatch('{^\s*(\S+).*?(\S+)\s*$}', $line, $match)) {
if (isset($match[1], $match[2]) && $match[2] !== './') {
$tags[rtrim($match[2], '/')] = $this->buildIdentifier(
'/' . $this->tagsPath . '/' . $match[2],
$match[1]
);
}
}
}
}
}

$this->tags = $tags;
}

return $this->tags;
}




public function getBranches(): array
{
if (null === $this->branches) {
$branches = [];

if (false === $this->trunkPath) {
$trunkParent = $this->baseUrl . '/';
} else {
$trunkParent = $this->baseUrl . '/' . $this->trunkPath;
}

$output = $this->execute('svn ls --verbose', $trunkParent);
if ($output) {
foreach ($this->process->splitLines($output) as $line) {
$line = trim($line);
if ($line && Preg::isMatch('{^\s*(\S+).*?(\S+)\s*$}', $line, $match)) {
if (isset($match[1], $match[2]) && $match[2] === './') {
$branches['trunk'] = $this->buildIdentifier(
'/' . $this->trunkPath,
$match[1]
);
$this->rootIdentifier = $branches['trunk'];
break;
}
}
}
}
unset($output);

if ($this->branchesPath !== false) {
$output = $this->execute('svn ls --verbose', $this->baseUrl . '/' . $this->branchesPath);
if ($output) {
foreach ($this->process->splitLines(trim($output)) as $line) {
$line = trim($line);
if ($line && Preg::isMatch('{^\s*(\S+).*?(\S+)\s*$}', $line, $match)) {
if (isset($match[1], $match[2]) && $match[2] !== './') {
$branches[rtrim($match[2], '/')] = $this->buildIdentifier(
'/' . $this->branchesPath . '/' . $match[2],
$match[1]
);
}
}
}
}
}

$this->branches = $branches;
}

return $this->branches;
}




public static function supports(IOInterface $io, Config $config, string $url, bool $deep = false): bool
{
$url = self::normalizeUrl($url);
if (Preg::isMatch('#(^svn://|^svn\+ssh://|svn\.)#i', $url)) {
return true;
}


if (!$deep && !Filesystem::isLocalPath($url)) {
return false;
}

$process = new ProcessExecutor($io);
$exit = $process->execute(
"svn info --non-interactive -- ".ProcessExecutor::escape($url),
$ignoredOutput
);

if ($exit === 0) {

return true;
}


if (false !== stripos($process->getErrorOutput(), 'authorization failed:')) {


return true;
}


if (false !== stripos($process->getErrorOutput(), 'Authentication failed')) {


return true;
}

return false;
}




protected static function normalizeUrl(string $url): string
{
$fs = new Filesystem();
if ($fs->isAbsolutePath($url)) {
return 'file://' . strtr($url, '\\', '/');
}

return $url;
}









protected function execute(string $command, string $url): string
{
if (null === $this->util) {
$this->util = new SvnUtil($this->baseUrl, $this->io, $this->config, $this->process);
$this->util->setCacheCredentials($this->cacheCredentials);
}

try {
return $this->util->execute($command, $url);
} catch (\RuntimeException $e) {
if (null === $this->util->binaryVersion()) {
throw new \RuntimeException('Failed to load '.$this->url.', svn was not found, check that it is installed and in your PATH env.' . "\n\n" . $this->process->getErrorOutput());
}

throw new \RuntimeException(
'Repository '.$this->url.' could not be processed, '.$e->getMessage()
);
}
}







protected function buildIdentifier(string $baseDir, string $revision): string
{
return rtrim($baseDir, '/') . $this->packagePath . '/@' . $revision;
}
}
