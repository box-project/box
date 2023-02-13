<?php declare(strict_types=1);











namespace Composer\Repository\Vcs;

use Composer\Config;
use Composer\Downloader\TransportException;
use Composer\Json\JsonFile;
use Composer\Cache;
use Composer\IO\IOInterface;
use Composer\Pcre\Preg;
use Composer\Util\GitHub;
use Composer\Util\Http\Response;




class GitHubDriver extends VcsDriver
{

protected $owner;

protected $repository;

protected $tags;

protected $branches;

protected $rootIdentifier;

protected $repoData;

protected $hasIssues = false;

protected $isPrivate = false;

private $isArchived = false;

private $fundingInfo;






protected $gitDriver = null;




public function initialize(): void
{
if (!Preg::isMatch('#^(?:(?:https?|git)://([^/]+)/|git@([^:]+):/?)([^/]+)/([^/]+?)(?:\.git|/)?$#', $this->url, $match)) {
throw new \InvalidArgumentException(sprintf('The GitHub repository URL %s is invalid.', $this->url));
}

assert(is_string($match[3]));
assert(is_string($match[4]));
$this->owner = $match[3];
$this->repository = $match[4];
$this->originUrl = strtolower($match[1] ?? (string) $match[2]);
if ($this->originUrl === 'www.github.com') {
$this->originUrl = 'github.com';
}
$this->cache = new Cache($this->io, $this->config->get('cache-repo-dir').'/'.$this->originUrl.'/'.$this->owner.'/'.$this->repository);
$this->cache->setReadOnly($this->config->get('cache-read-only'));

if ($this->config->get('use-github-api') === false || (isset($this->repoConfig['no-api']) && $this->repoConfig['no-api'])) {
$this->setupGitDriver($this->url);

return;
}

$this->fetchRootIdentifier();
}

public function getRepositoryUrl(): string
{
return 'https://'.$this->originUrl.'/'.$this->owner.'/'.$this->repository;
}




public function getRootIdentifier(): string
{
if ($this->gitDriver) {
return $this->gitDriver->getRootIdentifier();
}

return $this->rootIdentifier;
}




public function getUrl(): string
{
if ($this->gitDriver) {
return $this->gitDriver->getUrl();
}

return 'https://' . $this->originUrl . '/'.$this->owner.'/'.$this->repository.'.git';
}

protected function getApiUrl(): string
{
if ('github.com' === $this->originUrl) {
$apiUrl = 'api.github.com';
} else {
$apiUrl = $this->originUrl . '/api/v3';
}

return 'https://' . $apiUrl;
}




public function getSource(string $identifier): array
{
if ($this->gitDriver) {
return $this->gitDriver->getSource($identifier);
}
if ($this->isPrivate) {


$url = $this->generateSshUrl();
} else {
$url = $this->getUrl();
}

return ['type' => 'git', 'url' => $url, 'reference' => $identifier];
}




public function getDist(string $identifier): ?array
{
$url = $this->getApiUrl() . '/repos/'.$this->owner.'/'.$this->repository.'/zipball/'.$identifier;

return ['type' => 'zip', 'url' => $url, 'reference' => $identifier, 'shasum' => ''];
}




public function getComposerInformation(string $identifier): ?array
{
if ($this->gitDriver) {
return $this->gitDriver->getComposerInformation($identifier);
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
$label = array_search($identifier, $this->getTags()) ?: array_search($identifier, $this->getBranches()) ?: $identifier;
$composer['support']['source'] = sprintf('https://%s/%s/%s/tree/%s', $this->originUrl, $this->owner, $this->repository, $label);
}
if (!isset($composer['support']['issues']) && $this->hasIssues) {
$composer['support']['issues'] = sprintf('https://%s/%s/%s/issues', $this->originUrl, $this->owner, $this->repository);
}
if (!isset($composer['abandoned']) && $this->isArchived) {
$composer['abandoned'] = true;
}
if (!isset($composer['funding']) && $funding = $this->getFundingInfo()) {
$composer['funding'] = $funding;
}
}

$this->infoCache[$identifier] = $composer;
}

return $this->infoCache[$identifier];
}




private function getFundingInfo()
{
if (null !== $this->fundingInfo) {
return $this->fundingInfo;
}

if ($this->originUrl !== 'github.com') {
return $this->fundingInfo = false;
}

foreach ([$this->getApiUrl() . '/repos/'.$this->owner.'/'.$this->repository.'/contents/.github/FUNDING.yml', $this->getApiUrl() . '/repos/'.$this->owner.'/.github/contents/FUNDING.yml'] as $file) {
try {
$response = $this->httpDownloader->get($file, [
'retry-auth-failure' => false,
])->decodeJson();
} catch (TransportException $e) {
continue;
}
if (empty($response['content']) || $response['encoding'] !== 'base64' || !($funding = base64_decode($response['content']))) {
continue;
}
break;
}
if (empty($funding)) {
return $this->fundingInfo = false;
}

$result = [];
$key = null;
foreach (Preg::split('{\r?\n}', $funding) as $line) {
$line = trim($line);
if (Preg::isMatchStrictGroups('{^(\w+)\s*:\s*(.+)$}', $line, $match)) {
if ($match[2] === '[') {
$key = $match[1];
continue;
}
if (Preg::isMatchStrictGroups('{^\[(.*)\](?:\s*#.*)?$}', $match[2], $match2)) {
foreach (array_map('trim', Preg::split('{[\'"]?\s*,\s*[\'"]?}', $match2[1])) as $item) {
$result[] = ['type' => $match[1], 'url' => trim($item, '"\' ')];
}
} elseif (Preg::isMatchStrictGroups('{^([^#].*?)(?:\s+#.*)?$}', $match[2], $match2)) {
$result[] = ['type' => $match[1], 'url' => trim($match2[1], '"\' ')];
}
$key = null;
} elseif (Preg::isMatchStrictGroups('{^(\w+)\s*:\s*#\s*$}', $line, $match)) {
$key = $match[1];
} elseif ($key !== null && (
Preg::isMatchStrictGroups('{^-\s*(.+)(?:\s+#.*)?$}', $line, $match)
|| Preg::isMatchStrictGroups('{^(.+),(?:\s*#.*)?$}', $line, $match)
)) {
$result[] = ['type' => $key, 'url' => trim($match[1], '"\' ')];
} elseif ($key !== null && $line === ']') {
$key = null;
}
}

foreach ($result as $key => $item) {
switch ($item['type']) {
case 'tidelift':
$result[$key]['url'] = 'https://tidelift.com/funding/github/' . $item['url'];
break;
case 'github':
$result[$key]['url'] = 'https://github.com/' . basename($item['url']);
break;
case 'patreon':
$result[$key]['url'] = 'https://www.patreon.com/' . basename($item['url']);
break;
case 'otechie':
$result[$key]['url'] = 'https://otechie.com/' . basename($item['url']);
break;
case 'open_collective':
$result[$key]['url'] = 'https://opencollective.com/' . basename($item['url']);
break;
case 'liberapay':
$result[$key]['url'] = 'https://liberapay.com/' . basename($item['url']);
break;
case 'ko_fi':
$result[$key]['url'] = 'https://ko-fi.com/' . basename($item['url']);
break;
case 'issuehunt':
$result[$key]['url'] = 'https://issuehunt.io/r/' . $item['url'];
break;
case 'community_bridge':
$result[$key]['url'] = 'https://funding.communitybridge.org/projects/' . basename($item['url']);
break;
}
}

return $this->fundingInfo = $result;
}




public function getFileContent(string $file, string $identifier): ?string
{
if ($this->gitDriver) {
return $this->gitDriver->getFileContent($file, $identifier);
}

$resource = $this->getApiUrl() . '/repos/'.$this->owner.'/'.$this->repository.'/contents/' . $file . '?ref='.urlencode($identifier);
$resource = $this->getContents($resource)->decodeJson();



if ((!isset($resource['content']) || $resource['content'] === '') && $resource['encoding'] === 'none' && isset($resource['git_url'])) {
$resource = $this->getContents($resource['git_url'])->decodeJson();
}

if (empty($resource['content']) || $resource['encoding'] !== 'base64' || !($content = base64_decode($resource['content']))) {
throw new \RuntimeException('Could not retrieve ' . $file . ' for '.$identifier);
}

return $content;
}




public function getChangeDate(string $identifier): ?\DateTimeImmutable
{
if ($this->gitDriver) {
return $this->gitDriver->getChangeDate($identifier);
}

$resource = $this->getApiUrl() . '/repos/'.$this->owner.'/'.$this->repository.'/commits/'.urlencode($identifier);
$commit = $this->getContents($resource)->decodeJson();

return new \DateTimeImmutable($commit['commit']['committer']['date']);
}




public function getTags(): array
{
if ($this->gitDriver) {
return $this->gitDriver->getTags();
}
if (null === $this->tags) {
$tags = [];
$resource = $this->getApiUrl() . '/repos/'.$this->owner.'/'.$this->repository.'/tags?per_page=100';

do {
$response = $this->getContents($resource);
$tagsData = $response->decodeJson();
foreach ($tagsData as $tag) {
$tags[$tag['name']] = $tag['commit']['sha'];
}

$resource = $this->getNextPage($response);
} while ($resource);

$this->tags = $tags;
}

return $this->tags;
}




public function getBranches(): array
{
if ($this->gitDriver) {
return $this->gitDriver->getBranches();
}
if (null === $this->branches) {
$branches = [];
$resource = $this->getApiUrl() . '/repos/'.$this->owner.'/'.$this->repository.'/git/refs/heads?per_page=100';

do {
$response = $this->getContents($resource);
$branchData = $response->decodeJson();
foreach ($branchData as $branch) {
$name = substr($branch['ref'], 11);
if ($name !== 'gh-pages') {
$branches[$name] = $branch['object']['sha'];
}
}

$resource = $this->getNextPage($response);
} while ($resource);

$this->branches = $branches;
}

return $this->branches;
}




public static function supports(IOInterface $io, Config $config, string $url, bool $deep = false): bool
{
if (!Preg::isMatch('#^((?:https?|git)://([^/]+)/|git@([^:]+):/?)([^/]+)/([^/]+?)(?:\.git|/)?$#', $url, $matches)) {
return false;
}

$originUrl = $matches[2] ?? (string) $matches[3];
if (!in_array(strtolower(Preg::replace('{^www\.}i', '', $originUrl)), $config->get('github-domains'))) {
return false;
}

if (!extension_loaded('openssl')) {
$io->writeError('Skipping GitHub driver for '.$url.' because the OpenSSL PHP extension is missing.', true, IOInterface::VERBOSE);

return false;
}

return true;
}






public function getRepoData(): ?array
{
$this->fetchRootIdentifier();

return $this->repoData;
}




protected function generateSshUrl(): string
{
if (false !== strpos($this->originUrl, ':')) {
return 'ssh://git@' . $this->originUrl . '/'.$this->owner.'/'.$this->repository.'.git';
}

return 'git@' . $this->originUrl . ':'.$this->owner.'/'.$this->repository.'.git';
}




protected function getContents(string $url, bool $fetchingRepoData = false): Response
{
try {
return parent::getContents($url);
} catch (TransportException $e) {
$gitHubUtil = new GitHub($this->io, $this->config, $this->process, $this->httpDownloader);

switch ($e->getCode()) {
case 401:
case 404:

if (!$fetchingRepoData) {
throw $e;
}

if ($gitHubUtil->authorizeOAuth($this->originUrl)) {
return parent::getContents($url);
}

if (!$this->io->isInteractive()) {
$this->attemptCloneFallback();

return new Response(['url' => 'dummy'], 200, [], 'null');
}

$scopesIssued = [];
$scopesNeeded = [];
if ($headers = $e->getHeaders()) {
if ($scopes = Response::findHeaderValue($headers, 'X-OAuth-Scopes')) {
$scopesIssued = explode(' ', $scopes);
}
if ($scopes = Response::findHeaderValue($headers, 'X-Accepted-OAuth-Scopes')) {
$scopesNeeded = explode(' ', $scopes);
}
}
$scopesFailed = array_diff($scopesNeeded, $scopesIssued);


if (!$headers || !count($scopesNeeded) || count($scopesFailed)) {
$gitHubUtil->authorizeOAuthInteractively($this->originUrl, 'Your GitHub credentials are required to fetch private repository metadata (<info>'.$this->url.'</info>)');
}

return parent::getContents($url);

case 403:
if (!$this->io->hasAuthentication($this->originUrl) && $gitHubUtil->authorizeOAuth($this->originUrl)) {
return parent::getContents($url);
}

if (!$this->io->isInteractive() && $fetchingRepoData) {
$this->attemptCloneFallback();

return new Response(['url' => 'dummy'], 200, [], 'null');
}

$rateLimited = $gitHubUtil->isRateLimited((array) $e->getHeaders());

if (!$this->io->hasAuthentication($this->originUrl)) {
if (!$this->io->isInteractive()) {
$this->io->writeError('<error>GitHub API limit exhausted. Failed to get metadata for the '.$this->url.' repository, try running in interactive mode so that you can enter your GitHub credentials to increase the API limit</error>');
throw $e;
}

$gitHubUtil->authorizeOAuthInteractively($this->originUrl, 'API limit exhausted. Enter your GitHub credentials to get a larger API limit (<info>'.$this->url.'</info>)');

return parent::getContents($url);
}

if ($rateLimited) {
$rateLimit = $gitHubUtil->getRateLimit($e->getHeaders());
$this->io->writeError(sprintf(
'<error>GitHub API limit (%d calls/hr) is exhausted. You are already authorized so you have to wait until %s before doing more requests</error>',
$rateLimit['limit'],
$rateLimit['reset']
));
}

throw $e;

default:
throw $e;
}
}
}






protected function fetchRootIdentifier(): void
{
if ($this->repoData) {
return;
}

$repoDataUrl = $this->getApiUrl() . '/repos/'.$this->owner.'/'.$this->repository;

try {
$this->repoData = $this->getContents($repoDataUrl, true)->decodeJson();
} catch (TransportException $e) {
if ($e->getCode() === 499) {
$this->attemptCloneFallback();
} else {
throw $e;
}
}
if (null === $this->repoData && null !== $this->gitDriver) {
return;
}

$this->owner = $this->repoData['owner']['login'];
$this->repository = $this->repoData['name'];

$this->isPrivate = !empty($this->repoData['private']);
if (isset($this->repoData['default_branch'])) {
$this->rootIdentifier = $this->repoData['default_branch'];
} elseif (isset($this->repoData['master_branch'])) {
$this->rootIdentifier = $this->repoData['master_branch'];
} else {
$this->rootIdentifier = 'master';
}
$this->hasIssues = !empty($this->repoData['has_issues']);
$this->isArchived = !empty($this->repoData['archived']);
}







protected function attemptCloneFallback(): bool
{
$this->isPrivate = true;

try {




$this->setupGitDriver($this->generateSshUrl());

return true;
} catch (\RuntimeException $e) {
$this->gitDriver = null;

$this->io->writeError('<error>Failed to clone the '.$this->generateSshUrl().' repository, try running in interactive mode so that you can enter your GitHub credentials</error>');
throw $e;
}
}

protected function setupGitDriver(string $url): void
{
$this->gitDriver = new GitDriver(
['url' => $url],
$this->io,
$this->config,
$this->httpDownloader,
$this->process
);
$this->gitDriver->initialize();
}

protected function getNextPage(Response $response): ?string
{
$header = $response->getHeader('link');
if (!$header) {
return null;
}

$links = explode(',', $header);
foreach ($links as $link) {
if (Preg::isMatch('{<(.+?)>; *rel="next"}', $link, $match)) {
return $match[1];
}
}

return null;
}
}
