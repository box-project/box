<?php declare(strict_types=1);











namespace Composer\Util;

use Composer\Pcre\Preg;






class ComposerMirror
{




public static function processUrl(string $mirrorUrl, string $packageName, string $version, ?string $reference, ?string $type, ?string $prettyVersion = null): string
{
if ($reference) {
$reference = Preg::isMatch('{^([a-f0-9]*|%reference%)$}', $reference) ? $reference : md5($reference);
}
$version = strpos($version, '/') === false ? $version : md5($version);

$from = ['%package%', '%version%', '%reference%', '%type%'];
$to = [$packageName, $version, $reference, $type];
if (null !== $prettyVersion) {
$from[] = '%prettyVersion%';
$to[] = $prettyVersion;
}

$url = str_replace($from, $to, $mirrorUrl);
assert($url !== '');

return $url;
}





public static function processGitUrl(string $mirrorUrl, string $packageName, string $url, ?string $type): string
{
if (Preg::isMatch('#^(?:(?:https?|git)://github\.com/|git@github\.com:)([^/]+)/(.+?)(?:\.git)?$#', $url, $match)) {
$url = 'gh-'.$match[1].'/'.$match[2];
} elseif (Preg::isMatch('#^https://bitbucket\.org/([^/]+)/(.+?)(?:\.git)?/?$#', $url, $match)) {
$url = 'bb-'.$match[1].'/'.$match[2];
} else {
$url = Preg::replace('{[^a-z0-9_.-]}i', '-', trim($url, '/'));
}

return str_replace(
['%package%', '%normalizedUrl%', '%type%'],
[$packageName, $url, $type],
$mirrorUrl
);
}





public static function processHgUrl(string $mirrorUrl, string $packageName, string $url, string $type): string
{
return self::processGitUrl($mirrorUrl, $packageName, $url, $type);
}
}
