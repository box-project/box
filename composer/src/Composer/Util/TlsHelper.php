<?php declare(strict_types=1);











namespace Composer\Util;

use Composer\CaBundle\CaBundle;
use Composer\Pcre\Preg;





final class TlsHelper
{







public static function checkCertificateHost($certificate, string $hostname, ?string &$cn = null): bool
{
$names = self::getCertificateNames($certificate);

if (empty($names)) {
return false;
}

$combinedNames = array_merge($names['san'], [$names['cn']]);
$hostname = strtolower($hostname);

foreach ($combinedNames as $certName) {
$matcher = self::certNameMatcher($certName);

if ($matcher && $matcher($hostname)) {
$cn = $names['cn'];

return true;
}
}

return false;
}








public static function getCertificateNames($certificate): ?array
{
if (is_array($certificate)) {
$info = $certificate;
} elseif (CaBundle::isOpensslParseSafe()) {
$info = openssl_x509_parse($certificate, false);
}

if (!isset($info['subject']['commonName'])) {
return null;
}

$commonName = strtolower($info['subject']['commonName']);
$subjectAltNames = [];

if (isset($info['extensions']['subjectAltName'])) {
$subjectAltNames = Preg::split('{\s*,\s*}', $info['extensions']['subjectAltName']);
$subjectAltNames = array_filter(array_map(static function ($name): ?string {
if (0 === strpos($name, 'DNS:')) {
return strtolower(ltrim(substr($name, 4)));
}

return null;
}, $subjectAltNames));
$subjectAltNames = array_values($subjectAltNames);
}

return [
'cn' => $commonName,
'san' => $subjectAltNames,
];
}








































public static function getCertificateFingerprint(string $certificate): string
{
$pubkey = openssl_get_publickey($certificate);
if ($pubkey === false) {
throw new \RuntimeException('Failed to retrieve the public key from certificate');
}
$pubkeydetails = openssl_pkey_get_details($pubkey);
$pubkeypem = $pubkeydetails['key'];

$start = '-----BEGIN PUBLIC KEY-----';
$end = '-----END PUBLIC KEY-----';
$pemtrim = substr($pubkeypem, strpos($pubkeypem, $start) + strlen($start), (strlen($pubkeypem) - strpos($pubkeypem, $end)) * (-1));
$der = base64_decode($pemtrim);

return sha1($der);
}







public static function isOpensslParseSafe(): bool
{
return CaBundle::isOpensslParseSafe();
}






private static function certNameMatcher(string $certName): ?callable
{
$wildcards = substr_count($certName, '*');

if (0 === $wildcards) {

return static function ($hostname) use ($certName): bool {
return $hostname === $certName;
};
}

if (1 === $wildcards) {
$components = explode('.', $certName);

if (3 > count($components)) {

return null;
}

$firstComponent = $components[0];


if ('*' !== $firstComponent[strlen($firstComponent) - 1]) {
return null;
}

$wildcardRegex = preg_quote($certName);
$wildcardRegex = str_replace('\\*', '[a-z0-9-]+', $wildcardRegex);
$wildcardRegex = "{^{$wildcardRegex}$}";

return static function ($hostname) use ($wildcardRegex): bool {
return Preg::isMatch($wildcardRegex, $hostname);
};
}

return null;
}
}
