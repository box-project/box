<?php declare(strict_types=1);











namespace Composer\Util;

use Composer\Config;
use Composer\Pcre\Preg;




class Url
{




public static function updateDistReference(Config $config, string $url, string $ref): string
{
$host = parse_url($url, PHP_URL_HOST);

if ($host === 'api.github.com' || $host === 'github.com' || $host === 'www.github.com') {
if (Preg::isMatch('{^https?://(?:www\.)?github\.com/([^/]+)/([^/]+)/(zip|tar)ball/(.+)$}i', $url, $match)) {

$url = 'https://api.github.com/repos/' . $match[1] . '/'. $match[2] . '/' . $match[3] . 'ball/' . $ref;
} elseif (Preg::isMatch('{^https?://(?:www\.)?github\.com/([^/]+)/([^/]+)/archive/.+\.(zip|tar)(?:\.gz)?$}i', $url, $match)) {

$url = 'https://api.github.com/repos/' . $match[1] . '/'. $match[2] . '/' . $match[3] . 'ball/' . $ref;
} elseif (Preg::isMatch('{^https?://api\.github\.com/repos/([^/]+)/([^/]+)/(zip|tar)ball(?:/.+)?$}i', $url, $match)) {

$url = 'https://api.github.com/repos/' . $match[1] . '/'. $match[2] . '/' . $match[3] . 'ball/' . $ref;
}
} elseif ($host === 'bitbucket.org' || $host === 'www.bitbucket.org') {
if (Preg::isMatch('{^https?://(?:www\.)?bitbucket\.org/([^/]+)/([^/]+)/get/(.+)\.(zip|tar\.gz|tar\.bz2)$}i', $url, $match)) {

$url = 'https://bitbucket.org/' . $match[1] . '/'. $match[2] . '/get/' . $ref . '.' . $match[4];
}
} elseif ($host === 'gitlab.com' || $host === 'www.gitlab.com') {
if (Preg::isMatch('{^https?://(?:www\.)?gitlab\.com/api/v[34]/projects/([^/]+)/repository/archive\.(zip|tar\.gz|tar\.bz2|tar)\?sha=.+$}i', $url, $match)) {

$url = 'https://gitlab.com/api/v4/projects/' . $match[1] . '/repository/archive.' . $match[2] . '?sha=' . $ref;
}
} elseif (in_array($host, $config->get('github-domains'), true)) {
$url = Preg::replace('{(/repos/[^/]+/[^/]+/(zip|tar)ball)(?:/.+)?$}i', '$1/'.$ref, $url);
} elseif (in_array($host, $config->get('gitlab-domains'), true)) {
$url = Preg::replace('{(/api/v[34]/projects/[^/]+/repository/archive\.(?:zip|tar\.gz|tar\.bz2|tar)\?sha=).+$}i', '${1}'.$ref, $url);
}

assert($url !== '');

return $url;
}





public static function getOrigin(Config $config, string $url): string
{
if (0 === strpos($url, 'file://')) {
return $url;
}

$origin = (string) parse_url($url, PHP_URL_HOST);
if ($port = parse_url($url, PHP_URL_PORT)) {
$origin .= ':'.$port;
}

if (strpos($origin, '.github.com') === (strlen($origin) - 11)) {
return 'github.com';
}

if ($origin === 'repo.packagist.org') {
return 'packagist.org';
}

if ($origin === '') {
$origin = $url;
}



if (
false === strpos($origin, '/')
&& !in_array($origin, $config->get('gitlab-domains'), true)
) {
foreach ($config->get('gitlab-domains') as $gitlabDomain) {
if ($gitlabDomain !== '' && str_starts_with($gitlabDomain, $origin)) {
return $gitlabDomain;
}
}
}

return $origin;
}

public static function sanitize(string $url): string
{


$url = Preg::replace('{([&?]access_token=)[^&]+}', '$1***', $url);

$url = Preg::replaceCallback('{^(?P<prefix>[a-z0-9]+://)?(?P<user>[^:/\s@]+):(?P<password>[^@\s/]+)@}i', static function ($m): string {
assert(is_string($m['user']));

if (Preg::isMatch('{^([a-f0-9]{12,}|gh[a-z]_[a-zA-Z0-9_]+|github_pat_[a-zA-Z0-9_]+)$}', $m['user'])) {
return $m['prefix'].'***:***@';
}

return $m['prefix'].$m['user'].':***@';
}, $url);

return $url;
}
}
