<?php declare(strict_types=1);











namespace Composer\Repository\Vcs;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Cache;
use Composer\Downloader\TransportException;
use Composer\Json\JsonFile;
use Composer\Pcre\Preg;
use Composer\Util\Bitbucket;
use Composer\Util\Http\Response;




class GitBitbucketDriver extends VcsDriver
{

protected $owner;

protected $repository;

private $hasIssues = false;

private $rootIdentifier;

private $tags;

private $branches;

private $branchesUrl = '';

private $tagsUrl = '';

private $homeUrl = '';

private $website = '';

private $cloneHttpsUrl = '';

private $repoData;




protected $fallbackDriver = null;

private $vcsType;




public function initialize(): void
{
if (!Preg::isMatchStrictGroups('#^https?://bitbucket\.org/([^/]+)/([^/]+?)(?:\.git|/?)?$#i', $this->url, $match)) {
throw new \InvalidArgumentException(sprintf('The Bitbucket repository URL %s is invalid. It must be the HTTPS URL of a Bitbucket repository.', $this->url));
}

$this->owner = $match[1];
$this->repository = $match[2];
$this->originUrl = 'bitbucket.org';
$this->cache = new Cache(
$this->io,
implode('/', [
$this->config->get('cache-repo-dir'),
$this->originUrl,
$this->owner,
$this->repository,
])
);
$this->cache->setReadOnly($this->config->get('cache-read-only'));
}




public function getUrl(): string
{
if ($this->fallbackDriver) {
return $this->fallbackDriver->getUrl();
}

return $this->cloneHttpsUrl;
}







protected function getRepoData(): bool
{
$resource = sprintf(
'https://api.bitbucket.org/2.0/repositories/%s/%s?%s',
$this->owner,
$this->repository,
http_build_query(
['fields' => '-project,-owner'],
'',
'&'
)
);

$repoData = $this->fetchWithOAuthCredentials($resource, true)->decodeJson();
if ($this->fallbackDriver) {
return false;
}
$this->parseCloneUrls($repoData['links']['clone']);

$this->hasIssues = !empty($repoData['has_issues']);
$this->branchesUrl = $repoData['links']['branches']['href'];
$this->tagsUrl = $repoData['links']['tags']['href'];
$this->homeUrl = $repoData['links']['html']['href'];
$this->website = $repoData['website'];
$this->vcsType = $repoData['scm'];

$this->repoData = $repoData;

return true;
}




public function getComposerInformation(string $identifier): ?array
{
if ($this->fallbackDriver) {
return $this->fallbackDriver->getComposerInformation($identifier);
}

if (!isset($this->infoCache[$identifier])) {
if ($this->shouldCache($identifier) && $res = $this->cache->read($identifier)) {
$composer = JsonFile::parseJson($res);
} else {
$composer = $this->getBaseComposerInformation($identifier);

if ($this->shouldCache($identifier)) {
$this->cache->write($identifier, json_encode($composer));
}
}

if ($composer !== null) {

if (!isset($composer['support']['source'])) {
$label = array_search(
$identifier,
$this->getTags()
) ?: array_search(
$identifier,
$this->getBranches()
) ?: $identifier;

if (array_key_exists($label, $tags = $this->getTags())) {
$hash = $tags[$label];
} elseif (array_key_exists($label, $branches = $this->getBranches())) {
$hash = $branches[$label];
}

if (!isset($hash)) {
$composer['support']['source'] = sprintf(
'https://%s/%s/%s/src',
$this->originUrl,
$this->owner,
$this->repository
);
} else {
$composer['support']['source'] = sprintf(
'https://%s/%s/%s/src/%s/?at=%s',
$this->originUrl,
$this->owner,
$this->repository,
$hash,
$label
);
}
}
if (!isset($composer['support']['issues']) && $this->hasIssues) {
$composer['support']['issues'] = sprintf(
'https://%s/%s/%s/issues',
$this->originUrl,
$this->owner,
$this->repository
);
}
if (!isset($composer['homepage'])) {
$composer['homepage'] = empty($this->website) ? $this->homeUrl : $this->website;
}
}

$this->infoCache[$identifier] = $composer;
}

return $this->infoCache[$identifier];
}




public function getFileContent(string $file, string $identifier): ?string
{
if ($this->fallbackDriver) {
return $this->fallbackDriver->getFileContent($file, $identifier);
}

if (strpos($identifier, '/') !== false) {
$branches = $this->getBranches();
if (isset($branches[$identifier])) {
$identifier = $branches[$identifier];
}
}

$resource = sprintf(
'https://api.bitbucket.org/2.0/repositories/%s/%s/src/%s/%s',
$this->owner,
$this->repository,
$identifier,
$file
);

return $this->fetchWithOAuthCredentials($resource)->getBody();
}




public function getChangeDate(string $identifier): ?\DateTimeImmutable
{
if ($this->fallbackDriver) {
return $this->fallbackDriver->getChangeDate($identifier);
}

if (strpos($identifier, '/') !== false) {
$branches = $this->getBranches();
if (isset($branches[$identifier])) {
$identifier = $branches[$identifier];
}
}

$resource = sprintf(
'https://api.bitbucket.org/2.0/repositories/%s/%s/commit/%s?fields=date',
$this->owner,
$this->repository,
$identifier
);
$commit = $this->fetchWithOAuthCredentials($resource)->decodeJson();

return new \DateTimeImmutable($commit['date']);
}




public function getSource(string $identifier): array
{
if ($this->fallbackDriver) {
return $this->fallbackDriver->getSource($identifier);
}

return ['type' => $this->vcsType, 'url' => $this->getUrl(), 'reference' => $identifier];
}




public function getDist(string $identifier): ?array
{
if ($this->fallbackDriver) {
return $this->fallbackDriver->getDist($identifier);
}

$url = sprintf(
'https://bitbucket.org/%s/%s/get/%s.zip',
$this->owner,
$this->repository,
$identifier
);

return ['type' => 'zip', 'url' => $url, 'reference' => $identifier, 'shasum' => ''];
}




public function getTags(): array
{
if ($this->fallbackDriver) {
return $this->fallbackDriver->getTags();
}

if (null === $this->tags) {
$tags = [];
$resource = sprintf(
'%s?%s',
$this->tagsUrl,
http_build_query(
[
'pagelen' => 100,
'fields' => 'values.name,values.target.hash,next',
'sort' => '-target.date',
],
'',
'&'
)
);
$hasNext = true;
while ($hasNext) {
$tagsData = $this->fetchWithOAuthCredentials($resource)->decodeJson();
foreach ($tagsData['values'] as $data) {
$tags[$data['name']] = $data['target']['hash'];
}
if (empty($tagsData['next'])) {
$hasNext = false;
} else {
$resource = $tagsData['next'];
}
}

$this->tags = $tags;
}

return $this->tags;
}




public function getBranches(): array
{
if ($this->fallbackDriver) {
return $this->fallbackDriver->getBranches();
}

if (null === $this->branches) {
$branches = [];
$resource = sprintf(
'%s?%s',
$this->branchesUrl,
http_build_query(
[
'pagelen' => 100,
'fields' => 'values.name,values.target.hash,values.heads,next',
'sort' => '-target.date',
],
'',
'&'
)
);
$hasNext = true;
while ($hasNext) {
$branchData = $this->fetchWithOAuthCredentials($resource)->decodeJson();
foreach ($branchData['values'] as $data) {
$branches[$data['name']] = $data['target']['hash'];
}
if (empty($branchData['next'])) {
$hasNext = false;
} else {
$resource = $branchData['next'];
}
}

$this->branches = $branches;
}

return $this->branches;
}










protected function fetchWithOAuthCredentials(string $url, bool $fetchingRepoData = false): Response
{
try {
return parent::getContents($url);
} catch (TransportException $e) {
$bitbucketUtil = new Bitbucket($this->io, $this->config, $this->process, $this->httpDownloader);

if (in_array($e->getCode(), [403, 404], true) || (401 === $e->getCode() && strpos($e->getMessage(), 'Could not authenticate against') === 0)) {
if (!$this->io->hasAuthentication($this->originUrl)
&& $bitbucketUtil->authorizeOAuth($this->originUrl)
) {
return parent::getContents($url);
}

if (!$this->io->isInteractive() && $fetchingRepoData) {
$this->attemptCloneFallback();

return new Response(['url' => 'dummy'], 200, [], 'null');
}
}

throw $e;
}
}




protected function generateSshUrl(): string
{
return 'git@' . $this->originUrl . ':' . $this->owner.'/'.$this->repository.'.git';
}







protected function attemptCloneFallback(): bool
{
try {
$this->setupFallbackDriver($this->generateSshUrl());

return true;
} catch (\RuntimeException $e) {
$this->fallbackDriver = null;

$this->io->writeError(
'<error>Failed to clone the ' . $this->generateSshUrl() . ' repository, try running in interactive mode'
. ' so that you can enter your Bitbucket OAuth consumer credentials</error>'
);
throw $e;
}
}

protected function setupFallbackDriver(string $url): void
{
$this->fallbackDriver = new GitDriver(
['url' => $url],
$this->io,
$this->config,
$this->httpDownloader,
$this->process
);
$this->fallbackDriver->initialize();
}




protected function parseCloneUrls(array $cloneLinks): void
{
foreach ($cloneLinks as $cloneLink) {
if ($cloneLink['name'] === 'https') {


$this->cloneHttpsUrl = Preg::replace('/https:\/\/([^@]+@)?/', 'https://', $cloneLink['href']);
}
}
}




public function getRootIdentifier(): string
{
if ($this->fallbackDriver) {
return $this->fallbackDriver->getRootIdentifier();
}

if (null === $this->rootIdentifier) {
if (!$this->getRepoData()) {
if (!$this->fallbackDriver) {
throw new \LogicException('A fallback driver should be setup if getRepoData returns false');
}

return $this->fallbackDriver->getRootIdentifier();
}

if ($this->vcsType !== 'git') {
throw new \RuntimeException(
$this->url.' does not appear to be a git repository, use '.
$this->cloneHttpsUrl.' but remember that Bitbucket no longer supports the mercurial repositories. '.
'https://bitbucket.org/blog/sunsetting-mercurial-support-in-bitbucket'
);
}

$this->rootIdentifier = $this->repoData['mainbranch']['name'] ?? 'master';
}

return $this->rootIdentifier;
}




public static function supports(IOInterface $io, Config $config, string $url, bool $deep = false): bool
{
if (!Preg::isMatch('#^https?://bitbucket\.org/([^/]+)/([^/]+?)(\.git|/?)?$#i', $url)) {
return false;
}

if (!extension_loaded('openssl')) {
$io->writeError('Skipping Bitbucket git driver for '.$url.' because the OpenSSL PHP extension is missing.', true, IOInterface::VERBOSE);

return false;
}

return true;
}
}
