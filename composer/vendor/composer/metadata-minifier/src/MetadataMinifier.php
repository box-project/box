<?php










namespace Composer\MetadataMinifier;

class MetadataMinifier
{






public static function expand(array $versions)
{
$expanded = array();
$expandedVersion = null;
foreach ($versions as $versionData) {
if (!$expandedVersion) {
$expandedVersion = $versionData;
$expanded[] = $expandedVersion;
continue;
}


foreach ($versionData as $key => $val) {
if ($val === '__unset') {
unset($expandedVersion[$key]);
} else {
$expandedVersion[$key] = $val;
}
}

$expanded[] = $expandedVersion;
}

return $expanded;
}







public static function minify(array $versions)
{
$minifiedVersions = array();

$lastKnownVersionData = null;
foreach ($versions as $version) {
if (!$lastKnownVersionData) {
$lastKnownVersionData = $version;
$minifiedVersions[] = $version;
continue;
}

$minifiedVersion = array();


foreach ($version as $key => $val) {
if (!isset($lastKnownVersionData[$key]) || $lastKnownVersionData[$key] !== $val) {
$minifiedVersion[$key] = $val;
$lastKnownVersionData[$key] = $val;
}
}


foreach ($lastKnownVersionData as $key => $val) {
if (!isset($version[$key])) {
$minifiedVersion[$key] = "__unset";
unset($lastKnownVersionData[$key]);
}
}

$minifiedVersions[] = $minifiedVersion;
}

return $minifiedVersions;
}
}
