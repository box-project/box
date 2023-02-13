<?php declare(strict_types=1);











namespace Composer\Util\Http;







class ProxyHelper
{







public static function getProxyData(): array
{
$httpProxy = null;
$httpsProxy = null;


if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
if ($env = self::getProxyEnv(['http_proxy', 'HTTP_PROXY'], $name)) {
$httpProxy = self::checkProxy($env, $name);
}
}


if ($env = self::getProxyEnv(['CGI_HTTP_PROXY'], $name)) {
$httpProxy = self::checkProxy($env, $name);
}


if ($env = self::getProxyEnv(['https_proxy', 'HTTPS_PROXY'], $name)) {
$httpsProxy = self::checkProxy($env, $name);
} else {
$httpsProxy = $httpProxy;
}


$noProxy = self::getProxyEnv(['no_proxy', 'NO_PROXY'], $name);

return [$httpProxy, $httpsProxy, $noProxy];
}






public static function getContextOptions(string $proxyUrl): array
{
$proxy = parse_url($proxyUrl);


$proxyUrl = self::formatParsedUrl($proxy, false);
$proxyUrl = str_replace(['http://', 'https://'], ['tcp://', 'ssl://'], $proxyUrl);

$options['http']['proxy'] = $proxyUrl;


if (isset($proxy['user'])) {
$auth = rawurldecode($proxy['user']);

if (isset($proxy['pass'])) {
$auth .= ':' . rawurldecode($proxy['pass']);
}
$auth = base64_encode($auth);

$options['http']['header'] = "Proxy-Authorization: Basic {$auth}";
}

return $options;
}






public static function setRequestFullUri(string $requestUrl, array &$options): void
{
if ('http' === parse_url($requestUrl, PHP_URL_SCHEME)) {
$options['http']['request_fulluri'] = true;
} else {
unset($options['http']['request_fulluri']);
}
}









private static function getProxyEnv(array $names, ?string &$name): ?string
{
foreach ($names as $name) {
if (!empty($_SERVER[$name])) {
return $_SERVER[$name];
}
}

return null;
}







private static function checkProxy(string $proxyUrl, string $envName): string
{
$error = sprintf('malformed %s url', $envName);
$proxy = parse_url($proxyUrl);


if (!isset($proxy['host'])) {
throw new \RuntimeException($error);
}

$proxyUrl = self::formatParsedUrl($proxy, true);


if (!parse_url($proxyUrl, PHP_URL_PORT)) {
throw new \RuntimeException($error);
}

return $proxyUrl;
}








private static function formatParsedUrl(array $proxy, bool $includeAuth): string
{
$proxyUrl = isset($proxy['scheme']) ? strtolower($proxy['scheme']) . '://' : '';

if ($includeAuth && isset($proxy['user'])) {
$proxyUrl .= $proxy['user'];

if (isset($proxy['pass'])) {
$proxyUrl .= ':' . $proxy['pass'];
}
$proxyUrl .= '@';
}

$proxyUrl .= $proxy['host'];

if (isset($proxy['port'])) {
$proxyUrl .= ':' . $proxy['port'];
} elseif (strpos($proxyUrl, 'http://') === 0) {
$proxyUrl .= ':80';
} elseif (strpos($proxyUrl, 'https://') === 0) {
$proxyUrl .= ':443';
}

return $proxyUrl;
}
}
