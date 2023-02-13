<?php










namespace Composer\CaBundle;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\PhpProcess;





class CaBundle
{

private static $caPath;

private static $caFileValidity = array();

private static $useOpensslParse;






































public static function getSystemCaRootBundlePath(LoggerInterface $logger = null)
{
if (self::$caPath !== null) {
return self::$caPath;
}
$caBundlePaths = array();



$caBundlePaths[] = self::getEnvVariable('SSL_CERT_FILE');



$caBundlePaths[] = self::getEnvVariable('SSL_CERT_DIR');

$caBundlePaths[] = ini_get('openssl.cafile');
$caBundlePaths[] = ini_get('openssl.capath');

$otherLocations = array(
'/etc/pki/tls/certs/ca-bundle.crt', 
'/etc/ssl/certs/ca-certificates.crt', 
'/etc/ssl/ca-bundle.pem', 
'/usr/local/share/certs/ca-root-nss.crt', 
'/usr/ssl/certs/ca-bundle.crt', 
'/opt/local/share/curl/curl-ca-bundle.crt', 
'/usr/local/share/curl/curl-ca-bundle.crt', 
'/usr/share/ssl/certs/ca-bundle.crt', 
'/etc/ssl/cert.pem', 
'/usr/local/etc/ssl/cert.pem', 
'/usr/local/etc/openssl/cert.pem', 
'/usr/local/etc/openssl@1.1/cert.pem', 
);

foreach($otherLocations as $location) {
$otherLocations[] = dirname($location);
}

$caBundlePaths = array_merge($caBundlePaths, $otherLocations);

foreach ($caBundlePaths as $caBundle) {
if ($caBundle && self::caFileUsable($caBundle, $logger)) {
return self::$caPath = $caBundle;
}

if ($caBundle && self::caDirUsable($caBundle, $logger)) {
return self::$caPath = $caBundle;
}
}

return self::$caPath = static::getBundledCaBundlePath(); 
}








public static function getBundledCaBundlePath()
{
$caBundleFile = __DIR__.'/../res/cacert.pem';



if (0 === strpos($caBundleFile, 'phar://')) {
$tempCaBundleFile = tempnam(sys_get_temp_dir(), 'openssl-ca-bundle-');
if (false === $tempCaBundleFile) {
throw new \RuntimeException('Could not create a temporary file to store the bundled CA file');
}

file_put_contents(
$tempCaBundleFile,
file_get_contents($caBundleFile)
);

register_shutdown_function(function() use ($tempCaBundleFile) {
@unlink($tempCaBundleFile);
});

$caBundleFile = $tempCaBundleFile;
}

return $caBundleFile;
}









public static function validateCaFile($filename, LoggerInterface $logger = null)
{
static $warned = false;

if (isset(self::$caFileValidity[$filename])) {
return self::$caFileValidity[$filename];
}

$contents = file_get_contents($filename);



if (!static::isOpensslParseSafe()) {
if (!$warned && $logger) {
$logger->warning(sprintf(
'Your version of PHP, %s, is affected by CVE-2013-6420 and cannot safely perform certificate validation, we strongly suggest you upgrade.',
PHP_VERSION
));
$warned = true;
}

$isValid = !empty($contents);
} elseif (is_string($contents) && strlen($contents) > 0) {
$contents = preg_replace("/^(\\-+(?:BEGIN|END))\\s+TRUSTED\\s+(CERTIFICATE\\-+)\$/m", '$1 $2', $contents);
if (null === $contents) {

$isValid = false;
} else {
$isValid = (bool) openssl_x509_parse($contents);
}
} else {
$isValid = false;
}

if ($logger) {
$logger->debug('Checked CA file '.realpath($filename).': '.($isValid ? 'valid' : 'invalid'));
}

return self::$caFileValidity[$filename] = $isValid;
}









public static function isOpensslParseSafe()
{
if (null !== self::$useOpensslParse) {
return self::$useOpensslParse;
}

if (PHP_VERSION_ID >= 50600) {
return self::$useOpensslParse = true;
}





if (
(PHP_VERSION_ID < 50400 && PHP_VERSION_ID >= 50328)
|| (PHP_VERSION_ID < 50500 && PHP_VERSION_ID >= 50423)
|| PHP_VERSION_ID >= 50507
) {

return self::$useOpensslParse = true;
}

if (defined('PHP_WINDOWS_VERSION_BUILD')) {

return self::$useOpensslParse = false;
}

$compareDistroVersionPrefix = function ($prefix, $fixedVersion) {
$regex = '{^'.preg_quote($prefix).'([0-9]+)$}';

if (preg_match($regex, PHP_VERSION, $m)) {
return ((int) $m[1]) >= $fixedVersion;
}

return false;
};


if (
$compareDistroVersionPrefix('5.3.3-7+squeeze', 18) 
|| $compareDistroVersionPrefix('5.4.4-14+deb7u', 7) 
|| $compareDistroVersionPrefix('5.3.10-1ubuntu3.', 9) 
) {
return self::$useOpensslParse = true;
}


if (!class_exists('Symfony\Component\Process\PhpProcess')) {
return self::$useOpensslParse = false;
}










$cert = 'LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSUVwRENDQTR5Z0F3SUJBZ0lKQUp6dThyNnU2ZUJjTUEwR0NTcUdTSWIzRFFFQkJRVUFNSUhETVFzd0NRWUQKVlFRR0V3SkVSVEVjTUJvR0ExVUVDQXdUVG05eVpISm9aV2x1TFZkbGMzUm1ZV3hsYmpFUU1BNEdBMVVFQnd3SApTOE9Ed3Jac2JqRVVNQklHQTFVRUNnd0xVMlZyZEdsdmJrVnBibk14SHpBZEJnTlZCQXNNRmsxaGJHbGphVzkxCmN5QkRaWEowSUZObFkzUnBiMjR4SVRBZkJnTlZCQU1NR0cxaGJHbGphVzkxY3k1elpXdDBhVzl1WldsdWN5NWsKWlRFcU1DZ0dDU3FHU0liM0RRRUpBUlliYzNSbFptRnVMbVZ6YzJWeVFITmxhM1JwYjI1bGFXNXpMbVJsTUhVWQpaREU1TnpBd01UQXhNREF3TURBd1dnQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBCkFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUEKQUFBQUFBQVhEVEUwTVRFeU9ERXhNemt6TlZvd2djTXhDekFKQmdOVkJBWVRBa1JGTVJ3d0dnWURWUVFJREJOTwpiM0prY21obGFXNHRWMlZ6ZEdaaGJHVnVNUkF3RGdZRFZRUUhEQWRMdzRQQ3RteHVNUlF3RWdZRFZRUUtEQXRUClpXdDBhVzl1UldsdWN6RWZNQjBHQTFVRUN3d1dUV0ZzYVdOcGIzVnpJRU5sY25RZ1UyVmpkR2x2YmpFaE1COEcKQTFVRUF3d1liV0ZzYVdOcGIzVnpMbk5sYTNScGIyNWxhVzV6TG1SbE1Tb3dLQVlKS29aSWh2Y05BUWtCRmh0egpkR1ZtWVc0dVpYTnpaWEpBYzJWcmRHbHZibVZwYm5NdVpHVXdnZ0VpTUEwR0NTcUdTSWIzRFFFQkFRVUFBNElCCkR3QXdnZ0VLQW9JQkFRRERBZjNobDdKWTBYY0ZuaXlFSnBTU0RxbjBPcUJyNlFQNjV1c0pQUnQvOFBhRG9xQnUKd0VZVC9OYSs2ZnNnUGpDMHVLOURaZ1dnMnRIV1dvYW5TYmxBTW96NVBINlorUzRTSFJaN2UyZERJalBqZGhqaAowbUxnMlVNTzV5cDBWNzk3R2dzOWxOdDZKUmZIODFNTjJvYlhXczROdHp0TE11RDZlZ3FwcjhkRGJyMzRhT3M4CnBrZHVpNVVhd1Raa3N5NXBMUEhxNWNNaEZHbTA2djY1Q0xvMFYyUGQ5K0tBb2tQclBjTjVLTEtlYno3bUxwazYKU01lRVhPS1A0aWRFcXh5UTdPN2ZCdUhNZWRzUWh1K3ByWTNzaTNCVXlLZlF0UDVDWm5YMmJwMHdLSHhYMTJEWAoxbmZGSXQ5RGJHdkhUY3lPdU4rblpMUEJtM3ZXeG50eUlJdlZBZ01CQUFHalFqQkFNQWtHQTFVZEV3UUNNQUF3CkVRWUpZSVpJQVliNFFnRUJCQVFEQWdlQU1Bc0dBMVVkRHdRRUF3SUZvREFUQmdOVkhTVUVEREFLQmdnckJnRUYKQlFjREFqQU5CZ2txaGtpRzl3MEJBUVVGQUFPQ0FRRUFHMGZaWVlDVGJkajFYWWMrMVNub2FQUit2SThDOENhRAo4KzBVWWhkbnlVNGdnYTBCQWNEclk5ZTk0ZUVBdTZacXljRjZGakxxWFhkQWJvcHBXb2NyNlQ2R0QxeDMzQ2tsClZBcnpHL0t4UW9oR0QySmVxa2hJTWxEb214SE83a2EzOStPYThpMnZXTFZ5alU4QVp2V01BcnVIYTRFRU55RzcKbFcyQWFnYUZLRkNyOVRuWFRmcmR4R1ZFYnY3S1ZRNmJkaGc1cDVTanBXSDErTXEwM3VSM1pYUEJZZHlWODMxOQpvMGxWajFLRkkyRENML2xpV2lzSlJvb2YrMWNSMzVDdGQwd1lCY3BCNlRac2xNY09QbDc2ZHdLd0pnZUpvMlFnClpzZm1jMnZDMS9xT2xOdU5xLzBUenprVkd2OEVUVDNDZ2FVK1VYZTRYT1Z2a2NjZWJKbjJkZz09Ci0tLS0tRU5EIENFUlRJRklDQVRFLS0tLS0K';
$script = <<<'EOT'

error_reporting(-1);
$info = openssl_x509_parse(base64_decode('%s'));
var_dump(PHP_VERSION, $info['issuer']['emailAddress'], $info['validFrom_time_t']);

EOT;
$script = '<'."?php\n".sprintf($script, $cert);

try {
$process = new PhpProcess($script);
$process->mustRun();
} catch (\Exception $e) {


return self::$useOpensslParse = false;
}

$output = preg_split('{\r?\n}', trim($process->getOutput()));
$errorOutput = trim($process->getErrorOutput());

if (
is_array($output)
&& count($output) === 3
&& $output[0] === sprintf('string(%d) "%s"', strlen(PHP_VERSION), PHP_VERSION)
&& $output[1] === 'string(27) "stefan.esser@sektioneins.de"'
&& $output[2] === 'int(-1)'
&& preg_match('{openssl_x509_parse\(\): illegal (?:ASN1 data type for|length in) timestamp in - on line \d+}', $errorOutput)
) {

return self::$useOpensslParse = true;
}

return self::$useOpensslParse = false;
}





public static function reset()
{
self::$caFileValidity = array();
self::$caPath = null;
self::$useOpensslParse = null;
}





private static function getEnvVariable($name)
{
if (isset($_SERVER[$name])) {
return (string) $_SERVER[$name];
}

if (PHP_SAPI === 'cli' && ($value = getenv($name)) !== false && $value !== null) {
return (string) $value;
}

return false;
}






private static function caFileUsable($certFile, LoggerInterface $logger = null)
{
return $certFile
&& static::isFile($certFile, $logger)
&& static::isReadable($certFile, $logger)
&& static::validateCaFile($certFile, $logger);
}






private static function caDirUsable($certDir, LoggerInterface $logger = null)
{
return $certDir
&& static::isDir($certDir, $logger)
&& static::isReadable($certDir, $logger)
&& static::glob($certDir . '/*', $logger);
}






private static function isFile($certFile, LoggerInterface $logger = null)
{
$isFile = @is_file($certFile);
if (!$isFile && $logger) {
$logger->debug(sprintf('Checked CA file %s does not exist or it is not a file.', $certFile));
}

return $isFile;
}






private static function isDir($certDir, LoggerInterface $logger = null)
{
$isDir = @is_dir($certDir);
if (!$isDir && $logger) {
$logger->debug(sprintf('Checked directory %s does not exist or it is not a directory.', $certDir));
}

return $isDir;
}






private static function isReadable($certFileOrDir, LoggerInterface $logger = null)
{
$isReadable = @is_readable($certFileOrDir);
if (!$isReadable && $logger) {
$logger->debug(sprintf('Checked file or directory %s is not readable.', $certFileOrDir));
}

return $isReadable;
}






private static function glob($pattern, LoggerInterface $logger = null)
{
$certs = glob($pattern);
if ($certs === false) {
if ($logger) {
$logger->debug(sprintf("An error occurred while trying to find certificates for pattern: %s", $pattern));
}
return false;
}

if (count($certs) === 0) {
if ($logger) {
$logger->debug(sprintf("No CA files found for pattern: %s", $pattern));
}
return false;
}

return true;
}
}
