<?php declare(strict_types=1);











namespace Composer\Util;

use Composer\Composer;
use Composer\CaBundle\CaBundle;
use Composer\Downloader\TransportException;
use Composer\Repository\PlatformRepository;
use Composer\Util\Http\ProxyManager;
use Psr\Log\LoggerInterface;







final class StreamContextFactory
{










public static function getContext(string $url, array $defaultOptions = [], array $defaultParams = [])
{
$options = ['http' => [

'follow_location' => 1,
'max_redirects' => 20,
]];

$options = array_replace_recursive($options, self::initOptions($url, $defaultOptions));
unset($defaultOptions['http']['header']);
$options = array_replace_recursive($options, $defaultOptions);

if (isset($options['http']['header'])) {
$options['http']['header'] = self::fixHttpHeaderField($options['http']['header']);
}

return stream_context_create($options, $defaultParams);
}








public static function initOptions(string $url, array $options, bool $forCurl = false): array
{

if (!isset($options['http']['header'])) {
$options['http']['header'] = [];
}
if (is_string($options['http']['header'])) {
$options['http']['header'] = explode("\r\n", $options['http']['header']);
}


if (!$forCurl) {
$proxy = ProxyManager::getInstance()->getProxyForRequest($url);
if ($proxyOptions = $proxy->getContextOptions()) {
$isHttpsRequest = 0 === strpos($url, 'https://');

if ($proxy->isSecure()) {
if (!extension_loaded('openssl')) {
throw new TransportException('You must enable the openssl extension to use a secure proxy.');
}
if ($isHttpsRequest) {
throw new TransportException('You must enable the curl extension to make https requests through a secure proxy.');
}
} elseif ($isHttpsRequest && !extension_loaded('openssl')) {
throw new TransportException('You must enable the openssl extension to make https requests through a proxy.');
}


if (isset($proxyOptions['http']['header'])) {
$options['http']['header'][] = $proxyOptions['http']['header'];
unset($proxyOptions['http']['header']);
}
$options = array_replace_recursive($options, $proxyOptions);
}
}

if (defined('HHVM_VERSION')) {
$phpVersion = 'HHVM ' . HHVM_VERSION;
} else {
$phpVersion = 'PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
}

if ($forCurl) {
$curl = curl_version();
$httpVersion = 'cURL '.$curl['version'];
} else {
$httpVersion = 'streams';
}

if (!isset($options['http']['header']) || false === stripos(implode('', $options['http']['header']), 'user-agent')) {
$platformPhpVersion = PlatformRepository::getPlatformPhpVersion();
$options['http']['header'][] = sprintf(
'User-Agent: Composer/%s (%s; %s; %s; %s%s%s)',
Composer::getVersion(),
function_exists('php_uname') ? php_uname('s') : 'Unknown',
function_exists('php_uname') ? php_uname('r') : 'Unknown',
$phpVersion,
$httpVersion,
$platformPhpVersion ? '; Platform-PHP '.$platformPhpVersion : '',
Platform::getEnv('CI') ? '; CI' : ''
);
}

return $options;
}






public static function getTlsDefaults(array $options, ?LoggerInterface $logger = null): array
{
$ciphers = implode(':', [
'ECDHE-RSA-AES128-GCM-SHA256',
'ECDHE-ECDSA-AES128-GCM-SHA256',
'ECDHE-RSA-AES256-GCM-SHA384',
'ECDHE-ECDSA-AES256-GCM-SHA384',
'DHE-RSA-AES128-GCM-SHA256',
'DHE-DSS-AES128-GCM-SHA256',
'kEDH+AESGCM',
'ECDHE-RSA-AES128-SHA256',
'ECDHE-ECDSA-AES128-SHA256',
'ECDHE-RSA-AES128-SHA',
'ECDHE-ECDSA-AES128-SHA',
'ECDHE-RSA-AES256-SHA384',
'ECDHE-ECDSA-AES256-SHA384',
'ECDHE-RSA-AES256-SHA',
'ECDHE-ECDSA-AES256-SHA',
'DHE-RSA-AES128-SHA256',
'DHE-RSA-AES128-SHA',
'DHE-DSS-AES128-SHA256',
'DHE-RSA-AES256-SHA256',
'DHE-DSS-AES256-SHA',
'DHE-RSA-AES256-SHA',
'AES128-GCM-SHA256',
'AES256-GCM-SHA384',
'AES128-SHA256',
'AES256-SHA256',
'AES128-SHA',
'AES256-SHA',
'AES',
'CAMELLIA',
'DES-CBC3-SHA',
'!aNULL',
'!eNULL',
'!EXPORT',
'!DES',
'!RC4',
'!MD5',
'!PSK',
'!aECDH',
'!EDH-DSS-DES-CBC3-SHA',
'!EDH-RSA-DES-CBC3-SHA',
'!KRB5-DES-CBC3-SHA',
]);







$defaults = [
'ssl' => [
'ciphers' => $ciphers,
'verify_peer' => true,
'verify_depth' => 7,
'SNI_enabled' => true,
'capture_peer_cert' => true,
],
];

if (isset($options['ssl'])) {
$defaults['ssl'] = array_replace_recursive($defaults['ssl'], $options['ssl']);
}





if (!isset($defaults['ssl']['cafile']) && !isset($defaults['ssl']['capath'])) {
$result = CaBundle::getSystemCaRootBundlePath($logger);

if (is_dir($result)) {
$defaults['ssl']['capath'] = $result;
} else {
$defaults['ssl']['cafile'] = $result;
}
}

if (isset($defaults['ssl']['cafile']) && (!Filesystem::isReadable($defaults['ssl']['cafile']) || !CaBundle::validateCaFile($defaults['ssl']['cafile'], $logger))) {
throw new TransportException('The configured cafile was not valid or could not be read.');
}

if (isset($defaults['ssl']['capath']) && (!is_dir($defaults['ssl']['capath']) || !Filesystem::isReadable($defaults['ssl']['capath']))) {
throw new TransportException('The configured capath was not valid or could not be read.');
}




$defaults['ssl']['disable_compression'] = true;

return $defaults;
}











private static function fixHttpHeaderField($header): array
{
if (!is_array($header)) {
$header = explode("\r\n", $header);
}
uasort($header, static function ($el): int {
return stripos($el, 'content-type') === 0 ? 1 : -1;
});

return $header;
}
}
