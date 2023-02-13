<?php declare(strict_types=1);











namespace Composer\Package\Version;

use Composer\Pcre\Preg;
use Composer\Repository\PlatformRepository;
use Composer\Semver\VersionParser as SemverVersionParser;
use Composer\Semver\Semver;
use Composer\Semver\Constraint\ConstraintInterface;

class VersionParser extends SemverVersionParser
{
public const DEFAULT_BRANCH_ALIAS = '9999999-dev';


private static $constraints = [];




public function parseConstraints($constraints): ConstraintInterface
{
if (!isset(self::$constraints[$constraints])) {
self::$constraints[$constraints] = parent::parseConstraints($constraints);
}

return self::$constraints[$constraints];
}











public function parseNameVersionPairs(array $pairs): array
{
$pairs = array_values($pairs);
$result = [];

for ($i = 0, $count = count($pairs); $i < $count; $i++) {
$pair = Preg::replace('{^([^=: ]+)[=: ](.*)$}', '$1 $2', trim($pairs[$i]));
if (false === strpos($pair, ' ') && isset($pairs[$i + 1]) && false === strpos($pairs[$i + 1], '/') && !Preg::isMatch('{(?<=[a-z0-9_/-])\*|\*(?=[a-z0-9_/-])}i', $pairs[$i + 1]) && !PlatformRepository::isPlatformPackage($pairs[$i + 1])) {
$pair .= ' '.$pairs[$i + 1];
$i++;
}

if (strpos($pair, ' ')) {
[$name, $version] = explode(' ', $pair, 2);
$result[] = ['name' => $name, 'version' => $version];
} else {
$result[] = ['name' => $pair];
}
}

return $result;
}

public static function isUpgrade(string $normalizedFrom, string $normalizedTo): bool
{
if ($normalizedFrom === $normalizedTo) {
return true;
}

if (in_array($normalizedFrom, ['dev-master', 'dev-trunk', 'dev-default'], true)) {
$normalizedFrom = VersionParser::DEFAULT_BRANCH_ALIAS;
}
if (in_array($normalizedTo, ['dev-master', 'dev-trunk', 'dev-default'], true)) {
$normalizedTo = VersionParser::DEFAULT_BRANCH_ALIAS;
}

if (strpos($normalizedFrom, 'dev-') === 0 || strpos($normalizedTo, 'dev-') === 0) {
return true;
}

$sorted = Semver::sort([$normalizedTo, $normalizedFrom]);

return $sorted[0] === $normalizedFrom;
}
}
