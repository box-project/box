<?php declare(strict_types=1);











namespace Composer\Util;

use Composer\Config;
use Composer\Downloader\MaxFileSizeExceededException;
use Composer\IO\IOInterface;
use Composer\Downloader\TransportException;
use Composer\Pcre\Preg;
use Composer\Util\Http\Response;
use Composer\Util\Http\ProxyManager;







class RemoteFilesystem
{

private $io;

private $config;

private $scheme;

private $bytesMax;

private $originUrl;

private $fileUrl;

private $fileName;

private $retry = false;

private $progress;

private $lastProgress;

private $options = [];

private $disableTls = false;

private $lastHeaders;

private $storeAuth = false;

private $authHelper;

private $degradedMode = false;

private $redirects;

private $maxRedirects = 20;

private $proxyManager;









public function __construct(IOInterface $io, Config $config, array $options = [], bool $disableTls = false, ?AuthHelper $authHelper = null)
{
$this->io = $io;



if ($disableTls === false) {
$this->options = StreamContextFactory::getTlsDefaults($options, $io);
} else {
$this->disableTls = true;
}


$this->options = array_replace_recursive($this->options, $options);
$this->config = $config;
$this->authHelper = $authHelper ?? new AuthHelper($io, $config);
$this->proxyManager = ProxyManager::getInstance();
}












public function copy(string $originUrl, string $fileUrl, string $fileName, bool $progress = true, array $options = [])
{
return $this->get($originUrl, $fileUrl, $options, $fileName, $progress);
}











public function getContents(string $originUrl, string $fileUrl, bool $progress = true, array $options = [])
{
return $this->get($originUrl, $fileUrl, $options, null, $progress);
}






public function getOptions()
{
return $this->options;
}







public function setOptions(array $options)
{
$this->options = array_replace_recursive($this->options, $options);
}






public function isTlsDisabled()
{
return $this->disableTls === true;
}






public function getLastHeaders()
{
return $this->lastHeaders;
}





public static function findStatusCode(array $headers)
{
$value = null;
foreach ($headers as $header) {
if (Preg::isMatch('{^HTTP/\S+ (\d+)}i', $header, $match)) {


$value = (int) $match[1];
}
}

return $value;
}





public function findStatusMessage(array $headers)
{
$value = null;
foreach ($headers as $header) {
if (Preg::isMatch('{^HTTP/\S+ \d+}i', $header)) {


$value = $header;
}
}

return $value;
}















protected function get(string $originUrl, string $fileUrl, array $additionalOptions = [], ?string $fileName = null, bool $progress = true)
{
$this->scheme = parse_url(strtr($fileUrl, '\\', '/'), PHP_URL_SCHEME);
$this->bytesMax = 0;
$this->originUrl = $originUrl;
$this->fileUrl = $fileUrl;
$this->fileName = $fileName;
$this->progress = $progress;
$this->lastProgress = null;
$retryAuthFailure = true;
$this->lastHeaders = [];
$this->redirects = 1; 

$tempAdditionalOptions = $additionalOptions;
if (isset($tempAdditionalOptions['retry-auth-failure'])) {
$retryAuthFailure = (bool) $tempAdditionalOptions['retry-auth-failure'];

unset($tempAdditionalOptions['retry-auth-failure']);
}

$isRedirect = false;
if (isset($tempAdditionalOptions['redirects'])) {
$this->redirects = $tempAdditionalOptions['redirects'];
$isRedirect = true;

unset($tempAdditionalOptions['redirects']);
}

$options = $this->getOptionsForUrl($originUrl, $tempAdditionalOptions);
unset($tempAdditionalOptions);

$origFileUrl = $fileUrl;

if (isset($options['gitlab-token'])) {
$fileUrl .= (false === strpos($fileUrl, '?') ? '?' : '&') . 'access_token='.$options['gitlab-token'];
unset($options['gitlab-token']);
}

if (isset($options['http'])) {
$options['http']['ignore_errors'] = true;
}

if ($this->degradedMode && strpos($fileUrl, 'http://repo.packagist.org/') === 0) {

$fileUrl = 'http://' . gethostbyname('repo.packagist.org') . substr($fileUrl, 20);
$degradedPackagist = true;
}

$maxFileSize = null;
if (isset($options['max_file_size'])) {
$maxFileSize = $options['max_file_size'];
unset($options['max_file_size']);
}

$ctx = StreamContextFactory::getContext($fileUrl, $options, ['notification' => [$this, 'callbackGet']]);

$proxy = $this->proxyManager->getProxyForRequest($fileUrl);
$usingProxy = $proxy->getFormattedUrl(' using proxy (%s)');
$this->io->writeError((strpos($origFileUrl, 'http') === 0 ? 'Downloading ' : 'Reading ') . Url::sanitize($origFileUrl) . $usingProxy, true, IOInterface::DEBUG);
unset($origFileUrl, $proxy, $usingProxy);


if ((!Preg::isMatch('{^http://(repo\.)?packagist\.org/p/}', $fileUrl) || (false === strpos($fileUrl, '$') && false === strpos($fileUrl, '%24'))) && empty($degradedPackagist)) {
$this->config->prohibitUrlByConfig($fileUrl, $this->io);
}

if ($this->progress && !$isRedirect) {
$this->io->writeError("Downloading (<comment>connecting...</comment>)", false);
}

$errorMessage = '';
$errorCode = 0;
$result = false;
set_error_handler(static function ($code, $msg) use (&$errorMessage): bool {
if ($errorMessage) {
$errorMessage .= "\n";
}
$errorMessage .= Preg::replace('{^file_get_contents\(.*?\): }', '', $msg);

return true;
});
$http_response_header = [];
try {
$result = $this->getRemoteContents($originUrl, $fileUrl, $ctx, $http_response_header, $maxFileSize);

if (!empty($http_response_header[0])) {
$statusCode = self::findStatusCode($http_response_header);
if ($statusCode >= 400 && Response::findHeaderValue($http_response_header, 'content-type') === 'application/json') {
HttpDownloader::outputWarnings($this->io, $originUrl, json_decode($result, true));
}

if (in_array($statusCode, [401, 403]) && $retryAuthFailure) {
$this->promptAuthAndRetry($statusCode, $this->findStatusMessage($http_response_header), $http_response_header);
}
}

$contentLength = !empty($http_response_header[0]) ? Response::findHeaderValue($http_response_header, 'content-length') : null;
if ($contentLength && Platform::strlen($result) < $contentLength) {

$e = new TransportException('Content-Length mismatch, received '.Platform::strlen($result).' bytes out of the expected '.$contentLength);
$e->setHeaders($http_response_header);
$e->setStatusCode(self::findStatusCode($http_response_header));
try {
$e->setResponse($this->decodeResult($result, $http_response_header));
} catch (\Exception $discarded) {
$e->setResponse($this->normalizeResult($result));
}

$this->io->writeError('Content-Length mismatch, received '.Platform::strlen($result).' out of '.$contentLength.' bytes: (' . base64_encode($result).')', true, IOInterface::DEBUG);

throw $e;
}
} catch (\Exception $e) {
if ($e instanceof TransportException && !empty($http_response_header[0])) {
$e->setHeaders($http_response_header);
$e->setStatusCode(self::findStatusCode($http_response_header));
}
if ($e instanceof TransportException && $result !== false) {
$e->setResponse($this->decodeResult($result, $http_response_header));
}
$result = false;
}
if ($errorMessage && !filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
$errorMessage = 'allow_url_fopen must be enabled in php.ini ('.$errorMessage.')';
}
restore_error_handler();
if (isset($e) && !$this->retry) {
if (!$this->degradedMode && false !== strpos($e->getMessage(), 'Operation timed out')) {
$this->degradedMode = true;
$this->io->writeError('');
$this->io->writeError([
'<error>'.$e->getMessage().'</error>',
'<error>Retrying with degraded mode, check https://getcomposer.org/doc/articles/troubleshooting.md#degraded-mode for more info</error>',
]);

return $this->get($this->originUrl, $this->fileUrl, $additionalOptions, $this->fileName, $this->progress);
}

throw $e;
}

$statusCode = null;
$contentType = null;
$locationHeader = null;
if (!empty($http_response_header[0])) {
$statusCode = self::findStatusCode($http_response_header);
$contentType = Response::findHeaderValue($http_response_header, 'content-type');
$locationHeader = Response::findHeaderValue($http_response_header, 'location');
}


if ($originUrl === 'bitbucket.org'
&& !$this->authHelper->isPublicBitBucketDownload($fileUrl)
&& substr($fileUrl, -4) === '.zip'
&& (!$locationHeader || substr(parse_url($locationHeader, PHP_URL_PATH), -4) !== '.zip')
&& $contentType && Preg::isMatch('{^text/html\b}i', $contentType)
) {
$result = false;
if ($retryAuthFailure) {
$this->promptAuthAndRetry(401);
}
}


if ($statusCode === 404
&& in_array($originUrl, $this->config->get('gitlab-domains'), true)
&& false !== strpos($fileUrl, 'archive.zip')
) {
$result = false;
if ($retryAuthFailure) {
$this->promptAuthAndRetry(401);
}
}


$hasFollowedRedirect = false;
if ($statusCode >= 300 && $statusCode <= 399 && $statusCode !== 304 && $this->redirects < $this->maxRedirects) {
$hasFollowedRedirect = true;
$result = $this->handleRedirect($http_response_header, $additionalOptions, $result);
}


if ($statusCode && $statusCode >= 400 && $statusCode <= 599) {
if (!$this->retry) {
if ($this->progress && !$isRedirect) {
$this->io->overwriteError("Downloading (<error>failed</error>)", false);
}

$e = new TransportException('The "'.$this->fileUrl.'" file could not be downloaded ('.$http_response_header[0].')', $statusCode);
$e->setHeaders($http_response_header);
$e->setResponse($this->decodeResult($result, $http_response_header));
$e->setStatusCode($statusCode);
throw $e;
}
$result = false;
}

if ($this->progress && !$this->retry && !$isRedirect) {
$this->io->overwriteError("Downloading (".($result === false ? '<error>failed</error>' : '<comment>100%</comment>').")", false);
}


if ($result && extension_loaded('zlib') && strpos($fileUrl, 'http') === 0 && !$hasFollowedRedirect) {
try {
$result = $this->decodeResult($result, $http_response_header);
} catch (\Exception $e) {
if ($this->degradedMode) {
throw $e;
}

$this->degradedMode = true;
$this->io->writeError([
'',
'<error>Failed to decode response: '.$e->getMessage().'</error>',
'<error>Retrying with degraded mode, check https://getcomposer.org/doc/articles/troubleshooting.md#degraded-mode for more info</error>',
]);

return $this->get($this->originUrl, $this->fileUrl, $additionalOptions, $this->fileName, $this->progress);
}
}


if (false !== $result && null !== $fileName && !$isRedirect) {
if ('' === $result) {
throw new TransportException('"'.$this->fileUrl.'" appears broken, and returned an empty 200 response');
}

$errorMessage = '';
set_error_handler(static function ($code, $msg) use (&$errorMessage): bool {
if ($errorMessage) {
$errorMessage .= "\n";
}
$errorMessage .= Preg::replace('{^file_put_contents\(.*?\): }', '', $msg);

return true;
});
$result = (bool) file_put_contents($fileName, $result);
restore_error_handler();
if (false === $result) {
throw new TransportException('The "'.$this->fileUrl.'" file could not be written to '.$fileName.': '.$errorMessage);
}
}

if ($this->retry) {
$this->retry = false;

$result = $this->get($this->originUrl, $this->fileUrl, $additionalOptions, $this->fileName, $this->progress);

if ($this->storeAuth) {
$this->authHelper->storeAuth($this->originUrl, $this->storeAuth);
$this->storeAuth = false;
}

return $result;
}

if (false === $result) {
$e = new TransportException('The "'.$this->fileUrl.'" file could not be downloaded: '.$errorMessage, $errorCode);
if (!empty($http_response_header[0])) {
$e->setHeaders($http_response_header);
}

if (!$this->degradedMode && false !== strpos($e->getMessage(), 'Operation timed out')) {
$this->degradedMode = true;
$this->io->writeError('');
$this->io->writeError([
'<error>'.$e->getMessage().'</error>',
'<error>Retrying with degraded mode, check https://getcomposer.org/doc/articles/troubleshooting.md#degraded-mode for more info</error>',
]);

return $this->get($this->originUrl, $this->fileUrl, $additionalOptions, $this->fileName, $this->progress);
}

throw $e;
}

if (!empty($http_response_header[0])) {
$this->lastHeaders = $http_response_header;
}

return $result;
}












protected function getRemoteContents(string $originUrl, string $fileUrl, $context, ?array &$responseHeaders = null, ?int $maxFileSize = null)
{
$result = false;

try {
$e = null;
if ($maxFileSize !== null) {
$result = file_get_contents($fileUrl, false, $context, 0, $maxFileSize);
} else {

$result = file_get_contents($fileUrl, false, $context);
}
} catch (\Throwable $e) {
}

if ($result !== false && $maxFileSize !== null && Platform::strlen($result) >= $maxFileSize) {
throw new MaxFileSizeExceededException('Maximum allowed download size reached. Downloaded ' . Platform::strlen($result) . ' of allowed ' . $maxFileSize . ' bytes');
}


$responseHeaders = $http_response_header ?? [];

if (null !== $e) {
throw $e;
}

return $result;
}















protected function callbackGet(int $notificationCode, int $severity, ?string $message, int $messageCode, int $bytesTransferred, int $bytesMax)
{
switch ($notificationCode) {
case STREAM_NOTIFY_FAILURE:
if (400 === $messageCode) {


throw new TransportException("The '" . $this->fileUrl . "' URL could not be accessed: " . $message, $messageCode);
}
break;

case STREAM_NOTIFY_FILE_SIZE_IS:
$this->bytesMax = $bytesMax;
break;

case STREAM_NOTIFY_PROGRESS:
if ($this->bytesMax > 0 && $this->progress) {
$progression = min(100, (int) round($bytesTransferred / $this->bytesMax * 100));

if ((0 === $progression % 5) && 100 !== $progression && $progression !== $this->lastProgress) {
$this->lastProgress = $progression;
$this->io->overwriteError("Downloading (<comment>$progression%</comment>)", false);
}
}
break;

default:
break;
}
}







protected function promptAuthAndRetry($httpStatus, ?string $reason = null, array $headers = [])
{
$result = $this->authHelper->promptAuthIfNeeded($this->fileUrl, $this->originUrl, $httpStatus, $reason, $headers, 1 );

$this->storeAuth = $result['storeAuth'];
$this->retry = $result['retry'];

if ($this->retry) {
throw new TransportException('RETRY');
}
}






protected function getOptionsForUrl(string $originUrl, array $additionalOptions)
{
$tlsOptions = [];
$headers = [];

if (extension_loaded('zlib')) {
$headers[] = 'Accept-Encoding: gzip';
}

$options = array_replace_recursive($this->options, $tlsOptions, $additionalOptions);
if (!$this->degradedMode) {


$options['http']['protocol_version'] = 1.1;
$headers[] = 'Connection: close';
}

$headers = $this->authHelper->addAuthenticationHeader($headers, $originUrl, $this->fileUrl);

$options['http']['follow_location'] = 0;

if (isset($options['http']['header']) && !is_array($options['http']['header'])) {
$options['http']['header'] = explode("\r\n", trim($options['http']['header'], "\r\n"));
}
foreach ($headers as $header) {
$options['http']['header'][] = $header;
}

return $options;
}








private function handleRedirect(array $http_response_header, array $additionalOptions, $result)
{
if ($locationHeader = Response::findHeaderValue($http_response_header, 'location')) {
if (parse_url($locationHeader, PHP_URL_SCHEME)) {

$targetUrl = $locationHeader;
} elseif (parse_url($locationHeader, PHP_URL_HOST)) {

$targetUrl = $this->scheme.':'.$locationHeader;
} elseif ('/' === $locationHeader[0]) {

$urlHost = parse_url($this->fileUrl, PHP_URL_HOST);


$targetUrl = Preg::replace('{^(.+(?://|@)'.preg_quote($urlHost).'(?::\d+)?)(?:[/\?].*)?$}', '\1'.$locationHeader, $this->fileUrl);
} else {


$targetUrl = Preg::replace('{^(.+/)[^/?]*(?:\?.*)?$}', '\1'.$locationHeader, $this->fileUrl);
}
}

if (!empty($targetUrl)) {
$this->redirects++;

$this->io->writeError('', true, IOInterface::DEBUG);
$this->io->writeError(sprintf('Following redirect (%u) %s', $this->redirects, Url::sanitize($targetUrl)), true, IOInterface::DEBUG);

$additionalOptions['redirects'] = $this->redirects;

return $this->get(parse_url($targetUrl, PHP_URL_HOST), $targetUrl, $additionalOptions, $this->fileName, $this->progress);
}

if (!$this->retry) {
$e = new TransportException('The "'.$this->fileUrl.'" file could not be downloaded, got redirect without Location ('.$http_response_header[0].')');
$e->setHeaders($http_response_header);
$e->setResponse($this->decodeResult($result, $http_response_header));

throw $e;
}

return false;
}





private function decodeResult($result, array $http_response_header): ?string
{

if ($result && extension_loaded('zlib')) {
$contentEncoding = Response::findHeaderValue($http_response_header, 'content-encoding');
$decode = $contentEncoding && 'gzip' === strtolower($contentEncoding);

if ($decode) {
$result = zlib_decode($result);

if ($result === false) {
throw new TransportException('Failed to decode zlib stream');
}
}
}

return $this->normalizeResult($result);
}




private function normalizeResult($result): ?string
{
if ($result === false) {
return null;
}

return $result;
}
}
