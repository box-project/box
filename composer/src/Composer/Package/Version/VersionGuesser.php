<?php declare(strict_types=1);











namespace Composer\Package\Version;

use Composer\Config;
use Composer\Pcre\Preg;
use Composer\Repository\Vcs\HgDriver;
use Composer\IO\NullIO;
use Composer\Semver\VersionParser as SemverVersionParser;
use Composer\Util\Git as GitUtil;
use Composer\Util\HttpDownloader;
use Composer\Util\Platform;
use Composer\Util\ProcessExecutor;
use Composer\Util\Svn as SvnUtil;
use React\Promise\CancellablePromiseInterface;
use Symfony\Component\Process\Process;









class VersionGuesser
{



private $config;




private $process;




private $versionParser;

public function __construct(Config $config, ProcessExecutor $process, SemverVersionParser $versionParser)
{
$this->config = $config;
$this->process = $process;
$this->versionParser = $versionParser;
}







public function guessVersion(array $packageConfig, string $path): ?array
{
if (!function_exists('proc_open')) {
return null;
}



if (Platform::isInputCompletionProcess()) {
return null;
}

$versionData = $this->guessGitVersion($packageConfig, $path);
if (null !== $versionData && null !== $versionData['version']) {
return $this->postprocess($versionData);
}

$versionData = $this->guessHgVersion($packageConfig, $path);
if (null !== $versionData && null !== $versionData['version']) {
return $this->postprocess($versionData);
}

$versionData = $this->guessFossilVersion($path);
if (null !== $versionData && null !== $versionData['version']) {
return $this->postprocess($versionData);
}

$versionData = $this->guessSvnVersion($packageConfig, $path);
if (null !== $versionData && null !== $versionData['version']) {
return $this->postprocess($versionData);
}

return null;
}






private function postprocess(array $versionData): array
{
if (!empty($versionData['feature_version']) && $versionData['feature_version'] === $versionData['version'] && $versionData['feature_pretty_version'] === $versionData['pretty_version']) {
unset($versionData['feature_version'], $versionData['feature_pretty_version']);
}

if ('-dev' === substr($versionData['version'], -4) && Preg::isMatch('{\.9{7}}', $versionData['version'])) {
$versionData['pretty_version'] = Preg::replace('{(\.9{7})+}', '.x', $versionData['version']);
}

if (!empty($versionData['feature_version']) && '-dev' === substr($versionData['feature_version'], -4) && Preg::isMatch('{\.9{7}}', $versionData['feature_version'])) {
$versionData['feature_pretty_version'] = Preg::replace('{(\.9{7})+}', '.x', $versionData['feature_version']);
}

return $versionData;
}






private function guessGitVersion(array $packageConfig, string $path): array
{
GitUtil::cleanEnv();
$commit = null;
$version = null;
$prettyVersion = null;
$featureVersion = null;
$featurePrettyVersion = null;
$isDetached = false;


if (0 === $this->process->execute(['git', 'branch', '-a', '--no-color', '--no-abbrev', '-v'], $output, $path)) {
$branches = [];
$isFeatureBranch = false;


foreach ($this->process->splitLines($output) as $branch) {
if ($branch && Preg::isMatchStrictGroups('{^(?:\* ) *(\(no branch\)|\(detached from \S+\)|\(HEAD detached at \S+\)|\S+) *([a-f0-9]+) .*$}', $branch, $match)) {
if (
$match[1] === '(no branch)'
|| strpos($match[1], '(detached ') === 0
|| strpos($match[1], '(HEAD detached at') === 0
) {
$version = 'dev-' . $match[2];
$prettyVersion = $version;
$isFeatureBranch = true;
$isDetached = true;
} else {
$version = $this->versionParser->normalizeBranch($match[1]);
$prettyVersion = 'dev-' . $match[1];
$isFeatureBranch = $this->isFeatureBranch($packageConfig, $match[1]);
}

$commit = $match[2];
}

if ($branch && !Preg::isMatchStrictGroups('{^ *.+/HEAD }', $branch)) {
if (Preg::isMatchStrictGroups('{^(?:\* )? *((?:remotes/(?:origin|upstream)/)?[^\s/]+) *([a-f0-9]+) .*$}', $branch, $match)) {
$branches[] = $match[1];
}
}
}

if ($isFeatureBranch) {
$featureVersion = $version;
$featurePrettyVersion = $prettyVersion;


$result = $this->guessFeatureVersion($packageConfig, $version, $branches, 'git rev-list %candidate%..%branch%', $path);
$version = $result['version'];
$prettyVersion = $result['pretty_version'];
}
}

if (!$version || $isDetached) {
$result = $this->versionFromGitTags($path);
if ($result) {
$version = $result['version'];
$prettyVersion = $result['pretty_version'];
$featureVersion = null;
$featurePrettyVersion = null;
}
}

if (null === $commit) {
$command = 'git log --pretty="%H" -n1 HEAD'.GitUtil::getNoShowSignatureFlag($this->process);
if (0 === $this->process->execute($command, $output, $path)) {
$commit = trim($output) ?: null;
}
}

if ($featureVersion) {
return ['version' => $version, 'commit' => $commit, 'pretty_version' => $prettyVersion, 'feature_version' => $featureVersion, 'feature_pretty_version' => $featurePrettyVersion];
}

return ['version' => $version, 'commit' => $commit, 'pretty_version' => $prettyVersion];
}




private function versionFromGitTags(string $path): ?array
{

if (0 === $this->process->execute('git describe --exact-match --tags', $output, $path)) {
try {
$version = $this->versionParser->normalize(trim($output));

return ['version' => $version, 'pretty_version' => trim($output)];
} catch (\Exception $e) {
}
}

return null;
}






private function guessHgVersion(array $packageConfig, string $path): ?array
{

if (0 === $this->process->execute('hg branch', $output, $path)) {
$branch = trim($output);
$version = $this->versionParser->normalizeBranch($branch);
$isFeatureBranch = 0 === strpos($version, 'dev-');

if (VersionParser::DEFAULT_BRANCH_ALIAS === $version) {
return ['version' => $version, 'commit' => null, 'pretty_version' => 'dev-'.$branch];
}

if (!$isFeatureBranch) {
return ['version' => $version, 'commit' => null, 'pretty_version' => $version];
}


$io = new NullIO();
$driver = new HgDriver(['url' => $path], $io, $this->config, new HttpDownloader($io, $this->config), $this->process);
$branches = array_map('strval', array_keys($driver->getBranches()));


$result = $this->guessFeatureVersion($packageConfig, $version, $branches, 'hg log -r "not ancestors(\'%candidate%\') and ancestors(\'%branch%\')" --template "{node}\\n"', $path);
$result['commit'] = '';
$result['feature_version'] = $version;
$result['feature_pretty_version'] = $version;

return $result;
}

return null;
}









private function guessFeatureVersion(array $packageConfig, ?string $version, array $branches, string $scmCmdline, string $path): array
{
$prettyVersion = $version;



if (!isset($packageConfig['extra']['branch-alias'][$version])
|| strpos(json_encode($packageConfig), '"self.version"')
) {
$branch = Preg::replace('{^dev-}', '', $version);
$length = PHP_INT_MAX;


if (!$this->isFeatureBranch($packageConfig, $branch)) {
return ['version' => $version, 'pretty_version' => $prettyVersion];
}




usort($branches, static function ($a, $b): int {
$aRemote = 0 === strpos($a, 'remotes/');
$bRemote = 0 === strpos($b, 'remotes/');

if ($aRemote !== $bRemote) {
return $aRemote ? 1 : -1;
}

return strnatcasecmp($b, $a);
});

$promises = [];
$this->process->setMaxJobs(30);
try {
foreach ($branches as $candidate) {
$candidateVersion = Preg::replace('{^remotes/\S+/}', '', $candidate);


if ($candidate === $branch || $this->isFeatureBranch($packageConfig, $candidateVersion)) {
continue;
}

$cmdLine = str_replace(['%candidate%', '%branch%'], [$candidate, $branch], $scmCmdline);
$promises[] = $this->process->executeAsync($cmdLine, $path)->then(function (Process $process) use (&$length, &$version, &$prettyVersion, $candidateVersion, &$promises): void {
if (!$process->isSuccessful()) {
return;
}

$output = $process->getOutput();
if (strlen($output) < $length) {
$length = strlen($output);
$version = $this->versionParser->normalizeBranch($candidateVersion);
$prettyVersion = 'dev-' . $candidateVersion;
if ($length === 0) {
foreach ($promises as $promise) {
if ($promise instanceof CancellablePromiseInterface) {
$promise->cancel();
}
}
}
}
});
}

$this->process->wait();
} finally {
$this->process->resetMaxJobs();
}
}

return ['version' => $version, 'pretty_version' => $prettyVersion];
}




private function isFeatureBranch(array $packageConfig, ?string $branchName): bool
{
$nonFeatureBranches = '';
if (!empty($packageConfig['non-feature-branches'])) {
$nonFeatureBranches = implode('|', $packageConfig['non-feature-branches']);
}

return !Preg::isMatch('{^(' . $nonFeatureBranches . '|master|main|latest|next|current|support|tip|trunk|default|develop|\d+\..+)$}', $branchName, $match);
}




private function guessFossilVersion(string $path): array
{
$version = null;
$prettyVersion = null;


if (0 === $this->process->execute('fossil branch list', $output, $path)) {
$branch = trim($output);
$version = $this->versionParser->normalizeBranch($branch);
$prettyVersion = 'dev-' . $branch;
}


if (0 === $this->process->execute('fossil tag list', $output, $path)) {
try {
$version = $this->versionParser->normalize(trim($output));
$prettyVersion = trim($output);
} catch (\Exception $e) {
}
}

return ['version' => $version, 'commit' => '', 'pretty_version' => $prettyVersion];
}






private function guessSvnVersion(array $packageConfig, string $path): ?array
{
SvnUtil::cleanEnv();


if (0 === $this->process->execute('svn info --xml', $output, $path)) {
$trunkPath = isset($packageConfig['trunk-path']) ? preg_quote($packageConfig['trunk-path'], '#') : 'trunk';
$branchesPath = isset($packageConfig['branches-path']) ? preg_quote($packageConfig['branches-path'], '#') : 'branches';
$tagsPath = isset($packageConfig['tags-path']) ? preg_quote($packageConfig['tags-path'], '#') : 'tags';

$urlPattern = '#<url>.*/(' . $trunkPath . '|(' . $branchesPath . '|' . $tagsPath . ')/(.*))</url>#';

if (Preg::isMatch($urlPattern, $output, $matches)) {
if (isset($matches[2], $matches[3]) && ($branchesPath === $matches[2] || $tagsPath === $matches[2])) {

$version = $this->versionParser->normalizeBranch($matches[3]);
$prettyVersion = 'dev-' . $matches[3];

return ['version' => $version, 'commit' => '', 'pretty_version' => $prettyVersion];
}

assert(is_string($matches[1]));
$prettyVersion = trim($matches[1]);
if ($prettyVersion === 'trunk') {
$version = 'dev-trunk';
} else {
$version = $this->versionParser->normalize($prettyVersion);
}

return ['version' => $version, 'commit' => '', 'pretty_version' => $prettyVersion];
}
}

return null;
}
}
