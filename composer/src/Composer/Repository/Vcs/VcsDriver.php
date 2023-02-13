<?php declare(strict_types=1);











namespace Composer\Repository\Vcs;

use Composer\Cache;
use Composer\Downloader\TransportException;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Pcre\Preg;
use Composer\Util\ProcessExecutor;
use Composer\Util\HttpDownloader;
use Composer\Util\Filesystem;
use Composer\Util\Http\Response;






abstract class VcsDriver implements VcsDriverInterface
{

protected $url;

protected $originUrl;

protected $repoConfig;

protected $io;

protected $config;

protected $process;

protected $httpDownloader;

protected $infoCache = [];

protected $cache;










final public function __construct(array $repoConfig, IOInterface $io, Config $config, HttpDownloader $httpDownloader, ProcessExecutor $process)
{
if (Filesystem::isLocalPath($repoConfig['url'])) {
$repoConfig['url'] = Filesystem::getPlatformPath($repoConfig['url']);
}

$this->url = $repoConfig['url'];
$this->originUrl = $repoConfig['url'];
$this->repoConfig = $repoConfig;
$this->io = $io;
$this->config = $config;
$this->httpDownloader = $httpDownloader;
$this->process = $process;
}




protected function shouldCache(string $identifier): bool
{
return $this->cache && Preg::isMatch('{^[a-f0-9]{40}$}iD', $identifier);
}




public function getComposerInformation(string $identifier): ?array
{
if (!isset($this->infoCache[$identifier])) {
if ($this->shouldCache($identifier) && $res = $this->cache->read($identifier)) {
return $this->infoCache[$identifier] = JsonFile::parseJson($res);
}

$composer = $this->getBaseComposerInformation($identifier);

if ($this->shouldCache($identifier)) {
$this->cache->write($identifier, JsonFile::encode($composer, 0));
}

$this->infoCache[$identifier] = $composer;
}

return $this->infoCache[$identifier];
}




protected function getBaseComposerInformation(string $identifier): ?array
{
$composerFileContent = $this->getFileContent('composer.json', $identifier);

if (!$composerFileContent) {
return null;
}

$composer = JsonFile::parseJson($composerFileContent, $identifier . ':composer.json');

if ([] === $composer || !is_array($composer)) {
return null;
}

if (empty($composer['time']) && null !== ($changeDate = $this->getChangeDate($identifier))) {
$composer['time'] = $changeDate->format(DATE_RFC3339);
}

return $composer;
}




public function hasComposerFile(string $identifier): bool
{
try {
return null !== $this->getComposerInformation($identifier);
} catch (TransportException $e) {
}

return false;
}








protected function getScheme(): string
{
if (extension_loaded('openssl')) {
return 'https';
}

return 'http';
}








protected function getContents(string $url): Response
{
$options = $this->repoConfig['options'] ?? [];

return $this->httpDownloader->get($url, $options);
}




public function cleanup(): void
{
}
}
