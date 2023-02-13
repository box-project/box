<?php declare(strict_types=1);











namespace Composer\Util;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Downloader\TransportException;
use Composer\Pcre\Preg;
use Composer\Util\Http\Response;
use Composer\Util\Http\CurlDownloader;
use Composer\Composer;
use Composer\Package\Version\VersionParser;
use Composer\Semver\Constraint\Constraint;
use Composer\Exception\IrrecoverableDownloadException;
use React\Promise\Promise;
use React\Promise\PromiseInterface;






class HttpDownloader
{
private const STATUS_QUEUED = 1;
private const STATUS_STARTED = 2;
private const STATUS_COMPLETED = 3;
private const STATUS_FAILED = 4;
private const STATUS_ABORTED = 5;


private $io;

private $config;

private $jobs = [];

private $options = [];

private $runningJobs = 0;

private $maxJobs = 12;

private $curl;

private $rfs;

private $idGen = 0;

private $disabled;

private $allowAsync = false;






public function __construct(IOInterface $io, Config $config, array $options = [], bool $disableTls = false)
{
$this->io = $io;

$this->disabled = (bool) Platform::getEnv('COMPOSER_DISABLE_NETWORK');



if ($disableTls === false) {
$this->options = StreamContextFactory::getTlsDefaults($options, $io);
}


$this->options = array_replace_recursive($this->options, $options);
$this->config = $config;

if (self::isCurlEnabled()) {
$this->curl = new CurlDownloader($io, $config, $options, $disableTls);
}

$this->rfs = new RemoteFilesystem($io, $config, $options, $disableTls);

if (is_numeric($maxJobs = Platform::getEnv('COMPOSER_MAX_PARALLEL_HTTP'))) {
$this->maxJobs = max(1, min(50, (int) $maxJobs));
}
}










public function get(string $url, array $options = [])
{
if ('' === $url) {
throw new \InvalidArgumentException('$url must not be an empty string');
}
[$job] = $this->addJob(['url' => $url, 'options' => $options, 'copyTo' => null], true);
$this->wait($job['id']);

$response = $this->getResponse($job['id']);

return $response;
}










public function add(string $url, array $options = [])
{
if ('' === $url) {
throw new \InvalidArgumentException('$url must not be an empty string');
}
[, $promise] = $this->addJob(['url' => $url, 'options' => $options, 'copyTo' => null]);

return $promise;
}











public function copy(string $url, string $to, array $options = [])
{
if ('' === $url) {
throw new \InvalidArgumentException('$url must not be an empty string');
}
[$job] = $this->addJob(['url' => $url, 'options' => $options, 'copyTo' => $to], true);
$this->wait($job['id']);

return $this->getResponse($job['id']);
}











public function addCopy(string $url, string $to, array $options = [])
{
if ('' === $url) {
throw new \InvalidArgumentException('$url must not be an empty string');
}
[, $promise] = $this->addJob(['url' => $url, 'options' => $options, 'copyTo' => $to]);

return $promise;
}






public function getOptions()
{
return $this->options;
}







public function setOptions(array $options)
{
$this->options = array_replace_recursive($this->options, $options);
}





private function addJob(array $request, bool $sync = false): array
{
$request['options'] = array_replace_recursive($this->options, $request['options']);


$job = [
'id' => $this->idGen++,
'status' => self::STATUS_QUEUED,
'request' => $request,
'sync' => $sync,
'origin' => Url::getOrigin($this->config, $request['url']),
];

if (!$sync && !$this->allowAsync) {
throw new \LogicException('You must use the HttpDownloader instance which is part of a Composer\Loop instance to be able to run async http requests');
}


if (Preg::isMatchStrictGroups('{^https?://([^:/]+):([^@/]+)@([^/]+)}i', $request['url'], $match)) {
$this->io->setAuthentication($job['origin'], rawurldecode($match[1]), rawurldecode($match[2]));
}

$rfs = $this->rfs;

if ($this->canUseCurl($job)) {
$resolver = static function ($resolve, $reject) use (&$job): void {
$job['status'] = HttpDownloader::STATUS_QUEUED;
$job['resolve'] = $resolve;
$job['reject'] = $reject;
};
} else {
$resolver = static function ($resolve, $reject) use (&$job, $rfs): void {

$url = $job['request']['url'];
$options = $job['request']['options'];

$job['status'] = HttpDownloader::STATUS_STARTED;

if ($job['request']['copyTo']) {
$rfs->copy($job['origin'], $url, $job['request']['copyTo'], false , $options);

$headers = $rfs->getLastHeaders();
$response = new Http\Response($job['request'], $rfs->findStatusCode($headers), $headers, $job['request']['copyTo'].'~');

$resolve($response);
} else {
$body = $rfs->getContents($job['origin'], $url, false , $options);
$headers = $rfs->getLastHeaders();
$response = new Http\Response($job['request'], $rfs->findStatusCode($headers), $headers, $body);

$resolve($response);
}
};
}

$curl = $this->curl;

$canceler = static function () use (&$job, $curl): void {
if ($job['status'] === HttpDownloader::STATUS_QUEUED) {
$job['status'] = HttpDownloader::STATUS_ABORTED;
}
if ($job['status'] !== HttpDownloader::STATUS_STARTED) {
return;
}
$job['status'] = HttpDownloader::STATUS_ABORTED;
if (isset($job['curl_id'])) {
$curl->abortRequest($job['curl_id']);
}
throw new IrrecoverableDownloadException('Download of ' . Url::sanitize($job['request']['url']) . ' canceled');
};

$promise = new Promise($resolver, $canceler);
$promise = $promise->then(function ($response) use (&$job) {
$job['status'] = HttpDownloader::STATUS_COMPLETED;
$job['response'] = $response;

$this->markJobDone();

return $response;
}, function ($e) use (&$job): void {
$job['status'] = HttpDownloader::STATUS_FAILED;
$job['exception'] = $e;

$this->markJobDone();

throw $e;
});
$this->jobs[$job['id']] = &$job;

if ($this->runningJobs < $this->maxJobs) {
$this->startJob($job['id']);
}

return [$job, $promise];
}

private function startJob(int $id): void
{
$job = &$this->jobs[$id];
if ($job['status'] !== self::STATUS_QUEUED) {
return;
}


$job['status'] = self::STATUS_STARTED;
$this->runningJobs++;

assert(isset($job['resolve']));
assert(isset($job['reject']));

$resolve = $job['resolve'];
$reject = $job['reject'];
$url = $job['request']['url'];
$options = $job['request']['options'];
$origin = $job['origin'];

if ($this->disabled) {
if (isset($job['request']['options']['http']['header']) && false !== stripos(implode('', $job['request']['options']['http']['header']), 'if-modified-since')) {
$resolve(new Response(['url' => $url], 304, [], ''));
} else {
$e = new TransportException('Network disabled, request canceled: '.Url::sanitize($url), 499);
$e->setStatusCode(499);
$reject($e);
}

return;
}

try {
if ($job['request']['copyTo']) {
$job['curl_id'] = $this->curl->download($resolve, $reject, $origin, $url, $options, $job['request']['copyTo']);
} else {
$job['curl_id'] = $this->curl->download($resolve, $reject, $origin, $url, $options);
}
} catch (\Exception $exception) {
$reject($exception);
}
}

private function markJobDone(): void
{
$this->runningJobs--;
}








public function wait(?int $index = null)
{
do {
$jobCount = $this->countActiveJobs($index);
} while ($jobCount);
}




public function enableAsync(): void
{
$this->allowAsync = true;
}







public function countActiveJobs(?int $index = null): int
{
if ($this->runningJobs < $this->maxJobs) {
foreach ($this->jobs as $job) {
if ($job['status'] === self::STATUS_QUEUED && $this->runningJobs < $this->maxJobs) {
$this->startJob($job['id']);
}
}
}

if ($this->curl) {
$this->curl->tick();
}

if (null !== $index) {
return $this->jobs[$index]['status'] < self::STATUS_COMPLETED ? 1 : 0;
}

$active = 0;
foreach ($this->jobs as $job) {
if ($job['status'] < self::STATUS_COMPLETED) {
$active++;
} elseif (!$job['sync']) {
unset($this->jobs[$job['id']]);
}
}

return $active;
}




private function getResponse(int $index): Response
{
if (!isset($this->jobs[$index])) {
throw new \LogicException('Invalid request id');
}

if ($this->jobs[$index]['status'] === self::STATUS_FAILED) {
assert(isset($this->jobs[$index]['exception']));
throw $this->jobs[$index]['exception'];
}

if (!isset($this->jobs[$index]['response'])) {
throw new \LogicException('Response not available yet, call wait() first');
}

$resp = $this->jobs[$index]['response'];

unset($this->jobs[$index]);

return $resp;
}






public static function outputWarnings(IOInterface $io, string $url, $data): void
{
$cleanMessage = static function ($msg) use ($io) {
if (!$io->isDecorated()) {
$msg = Preg::replace('{'.chr(27).'\\[[;\d]*m}u', '', $msg);
}

return $msg;
};


foreach (['warning', 'info'] as $type) {
if (empty($data[$type])) {
continue;
}

if (!empty($data[$type . '-versions'])) {
$versionParser = new VersionParser();
$constraint = $versionParser->parseConstraints($data[$type . '-versions']);
$composer = new Constraint('==', $versionParser->normalize(Composer::getVersion()));
if (!$constraint->matches($composer)) {
continue;
}
}

$io->writeError('<'.$type.'>'.ucfirst($type).' from '.Url::sanitize($url).': '.$cleanMessage($data[$type]).'</'.$type.'>');
}


foreach (['warnings', 'infos'] as $key) {
if (empty($data[$key])) {
continue;
}

$versionParser = new VersionParser();
foreach ($data[$key] as $spec) {
$type = substr($key, 0, -1);
$constraint = $versionParser->parseConstraints($spec['versions']);
$composer = new Constraint('==', $versionParser->normalize(Composer::getVersion()));
if (!$constraint->matches($composer)) {
continue;
}

$io->writeError('<'.$type.'>'.ucfirst($type).' from '.Url::sanitize($url).': '.$cleanMessage($spec['message']).'</'.$type.'>');
}
}
}






public static function getExceptionHints(\Throwable $e): ?array
{
if (!$e instanceof TransportException) {
return null;
}

if (
false !== strpos($e->getMessage(), 'Resolving timed out')
|| false !== strpos($e->getMessage(), 'Could not resolve host')
) {
Silencer::suppress();
$testConnectivity = file_get_contents('https://8.8.8.8', false, stream_context_create([
'ssl' => ['verify_peer' => false],
'http' => ['follow_location' => false, 'ignore_errors' => true],
]));
Silencer::restore();
if (false !== $testConnectivity) {
return [
'<error>The following exception probably indicates you have misconfigured DNS resolver(s)</error>',
];
}

return [
'<error>The following exception probably indicates you are offline or have misconfigured DNS resolver(s)</error>',
];
}

return null;
}




private function canUseCurl(array $job): bool
{
if (!$this->curl) {
return false;
}

if (!Preg::isMatch('{^https?://}i', $job['request']['url'])) {
return false;
}

if (!empty($job['request']['options']['ssl']['allow_self_signed'])) {
return false;
}

return true;
}




public static function isCurlEnabled(): bool
{
return \extension_loaded('curl') && \function_exists('curl_multi_exec') && \function_exists('curl_multi_init');
}
}
