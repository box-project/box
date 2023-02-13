<?php declare(strict_types=1);











namespace Composer\Downloader;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Pcre\Preg;
use Composer\Util\Filesystem;
use Composer\Util\Git as GitUtil;
use Composer\Util\Url;
use Composer\Util\Platform;
use Composer\Util\ProcessExecutor;
use Composer\Cache;
use React\Promise\PromiseInterface;




class GitDownloader extends VcsDownloader implements DvcsDownloaderInterface
{




private $hasStashedChanges = [];




private $hasDiscardedChanges = [];



private $gitUtil;




private $cachedPackages = [];

public function __construct(IOInterface $io, Config $config, ?ProcessExecutor $process = null, ?Filesystem $fs = null)
{
parent::__construct($io, $config, $process, $fs);
$this->gitUtil = new GitUtil($this->io, $this->config, $this->process, $this->filesystem);
}




protected function doDownload(PackageInterface $package, string $path, string $url, ?PackageInterface $prevPackage = null): PromiseInterface
{

if (Filesystem::isLocalPath($url)) {
return \React\Promise\resolve(null);
}

GitUtil::cleanEnv();

$cachePath = $this->config->get('cache-vcs-dir').'/'.Preg::replace('{[^a-z0-9.]}i', '-', $url).'/';
$gitVersion = GitUtil::getVersion($this->process);


if ($gitVersion && version_compare($gitVersion, '2.3.0-rc0', '>=') && Cache::isUsable($cachePath)) {
$this->io->writeError("  - Syncing <info>" . $package->getName() . "</info> (<comment>" . $package->getFullPrettyVersion() . "</comment>) into cache");
$this->io->writeError(sprintf('    Cloning to cache at %s', ProcessExecutor::escape($cachePath)), true, IOInterface::DEBUG);
$ref = $package->getSourceReference();
if ($this->gitUtil->fetchRefOrSyncMirror($url, $cachePath, $ref, $package->getPrettyVersion()) && is_dir($cachePath)) {
$this->cachedPackages[$package->getId()][$ref] = true;
}
} elseif (null === $gitVersion) {
throw new \RuntimeException('git was not found in your PATH, skipping source download');
}

return \React\Promise\resolve(null);
}




protected function doInstall(PackageInterface $package, string $path, string $url): PromiseInterface
{
GitUtil::cleanEnv();
$path = $this->normalizePath($path);
$cachePath = $this->config->get('cache-vcs-dir').'/'.Preg::replace('{[^a-z0-9.]}i', '-', $url).'/';
$ref = $package->getSourceReference();
$flag = Platform::isWindows() ? '/D ' : '';

if (!empty($this->cachedPackages[$package->getId()][$ref])) {
$msg = "Cloning ".$this->getShortHash($ref).' from cache';

$cloneFlags = '--dissociate --reference %cachePath% ';
$transportOptions = $package->getTransportOptions();
if (isset($transportOptions['git']['single_use_clone']) && $transportOptions['git']['single_use_clone']) {
$cloneFlags = '';
}

$command =
'git clone --no-checkout %cachePath% %path% ' . $cloneFlags
. '&& cd '.$flag.'%path% '
. '&& git remote set-url origin -- %sanitizedUrl% && git remote add composer -- %sanitizedUrl%';
} else {
$msg = "Cloning ".$this->getShortHash($ref);
$command = 'git clone --no-checkout -- %url% %path% && cd '.$flag.'%path% && git remote add composer -- %url% && git fetch composer && git remote set-url origin -- %sanitizedUrl% && git remote set-url composer -- %sanitizedUrl%';
if (Platform::getEnv('COMPOSER_DISABLE_NETWORK')) {
throw new \RuntimeException('The required git reference for '.$package->getName().' is not in cache and network is disabled, aborting');
}
}

$this->io->writeError($msg);

$commandCallable = static function (string $url) use ($path, $command, $cachePath): string {
return str_replace(
['%url%', '%path%', '%cachePath%', '%sanitizedUrl%'],
[
ProcessExecutor::escape($url),
ProcessExecutor::escape($path),
ProcessExecutor::escape($cachePath),
ProcessExecutor::escape(Preg::replace('{://([^@]+?):(.+?)@}', '://', $url)),
],
$command
);
};

$this->gitUtil->runCommand($commandCallable, $url, $path, true);
$sourceUrl = $package->getSourceUrl();
if ($url !== $sourceUrl && $sourceUrl !== null) {
$this->updateOriginUrl($path, $sourceUrl);
} else {
$this->setPushUrl($path, $url);
}

if ($newRef = $this->updateToCommit($package, $path, (string) $ref, $package->getPrettyVersion())) {
if ($package->getDistReference() === $package->getSourceReference()) {
$package->setDistReference($newRef);
}
$package->setSourceReference($newRef);
}

return \React\Promise\resolve(null);
}




protected function doUpdate(PackageInterface $initial, PackageInterface $target, string $path, string $url): PromiseInterface
{
GitUtil::cleanEnv();
$path = $this->normalizePath($path);
if (!$this->hasMetadataRepository($path)) {
throw new \RuntimeException('The .git directory is missing from '.$path.', see https://getcomposer.org/commit-deps for more information');
}

$cachePath = $this->config->get('cache-vcs-dir').'/'.Preg::replace('{[^a-z0-9.]}i', '-', $url).'/';
$ref = $target->getSourceReference();

if (!empty($this->cachedPackages[$target->getId()][$ref])) {
$msg = "Checking out ".$this->getShortHash($ref).' from cache';
$command = '(git rev-parse --quiet --verify %ref% || (git remote set-url composer -- %cachePath% && git fetch composer && git fetch --tags composer)) && git remote set-url composer -- %sanitizedUrl%';
} else {
$msg = "Checking out ".$this->getShortHash($ref);
$command = '(git remote set-url composer -- %url% && git rev-parse --quiet --verify %ref% || (git fetch composer && git fetch --tags composer)) && git remote set-url composer -- %sanitizedUrl%';
if (Platform::getEnv('COMPOSER_DISABLE_NETWORK')) {
throw new \RuntimeException('The required git reference for '.$target->getName().' is not in cache and network is disabled, aborting');
}
}

$this->io->writeError($msg);

$commandCallable = static function ($url) use ($ref, $command, $cachePath): string {
return str_replace(
['%url%', '%ref%', '%cachePath%', '%sanitizedUrl%'],
[
ProcessExecutor::escape($url),
ProcessExecutor::escape($ref.'^{commit}'),
ProcessExecutor::escape($cachePath),
ProcessExecutor::escape(Preg::replace('{://([^@]+?):(.+?)@}', '://', $url)),
],
$command
);
};

$this->gitUtil->runCommand($commandCallable, $url, $path);
if ($newRef = $this->updateToCommit($target, $path, (string) $ref, $target->getPrettyVersion())) {
if ($target->getDistReference() === $target->getSourceReference()) {
$target->setDistReference($newRef);
}
$target->setSourceReference($newRef);
}

$updateOriginUrl = false;
if (
0 === $this->process->execute('git remote -v', $output, $path)
&& Preg::isMatch('{^origin\s+(?P<url>\S+)}m', $output, $originMatch)
&& Preg::isMatch('{^composer\s+(?P<url>\S+)}m', $output, $composerMatch)
) {
if ($originMatch['url'] === $composerMatch['url'] && $composerMatch['url'] !== $target->getSourceUrl()) {
$updateOriginUrl = true;
}
}
if ($updateOriginUrl && $target->getSourceUrl() !== null) {
$this->updateOriginUrl($path, $target->getSourceUrl());
}

return \React\Promise\resolve(null);
}




public function getLocalChanges(PackageInterface $package, string $path): ?string
{
GitUtil::cleanEnv();
if (!$this->hasMetadataRepository($path)) {
return null;
}

$command = 'git status --porcelain --untracked-files=no';
if (0 !== $this->process->execute($command, $output, $path)) {
throw new \RuntimeException('Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput());
}

$output = trim($output);

return strlen($output) > 0 ? $output : null;
}

public function getUnpushedChanges(PackageInterface $package, string $path): ?string
{
GitUtil::cleanEnv();
$path = $this->normalizePath($path);
if (!$this->hasMetadataRepository($path)) {
return null;
}

$command = 'git show-ref --head -d';
if (0 !== $this->process->execute($command, $output, $path)) {
throw new \RuntimeException('Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput());
}

$refs = trim($output);
if (!Preg::isMatchStrictGroups('{^([a-f0-9]+) HEAD$}mi', $refs, $match)) {

return null;
}

$headRef = $match[1];
if (!Preg::isMatchAllStrictGroups('{^'.$headRef.' refs/heads/(.+)$}mi', $refs, $matches)) {

return null;
}

$candidateBranches = $matches[1];

$branch = $candidateBranches[0];
$unpushedChanges = null;
$branchNotFoundError = false;


for ($i = 0; $i <= 1; $i++) {
$remoteBranches = [];


foreach ($candidateBranches as $candidate) {
if (Preg::isMatchAllStrictGroups('{^[a-f0-9]+ refs/remotes/((?:[^/]+)/'.preg_quote($candidate).')$}mi', $refs, $matches)) {
foreach ($matches[1] as $match) {
$branch = $candidate;
$remoteBranches[] = $match;
}
break;
}
}




if (count($remoteBranches) === 0) {
$unpushedChanges = 'Branch ' . $branch . ' could not be found on any remote and appears to be unpushed';
$branchNotFoundError = true;
} else {


if ($branchNotFoundError) {
$unpushedChanges = null;
}
foreach ($remoteBranches as $remoteBranch) {
$command = sprintf('git diff --name-status %s...%s --', $remoteBranch, $branch);
if (0 !== $this->process->execute($command, $output, $path)) {
throw new \RuntimeException('Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput());
}

$output = trim($output);

if ($unpushedChanges === null || strlen($output) < strlen($unpushedChanges)) {
$unpushedChanges = $output;
}
}
}



if ($unpushedChanges && $i === 0) {
$this->process->execute('git fetch --all', $output, $path);


$command = 'git show-ref --head -d';
if (0 !== $this->process->execute($command, $output, $path)) {
throw new \RuntimeException('Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput());
}
$refs = trim($output);
}


if (!$unpushedChanges) {
break;
}
}

return $unpushedChanges;
}




protected function cleanChanges(PackageInterface $package, string $path, bool $update): PromiseInterface
{
GitUtil::cleanEnv();
$path = $this->normalizePath($path);

$unpushed = $this->getUnpushedChanges($package, $path);
if ($unpushed && ($this->io->isInteractive() || $this->config->get('discard-changes') !== true)) {
throw new \RuntimeException('Source directory ' . $path . ' has unpushed changes on the current branch: '."\n".$unpushed);
}

if (null === ($changes = $this->getLocalChanges($package, $path))) {
return \React\Promise\resolve(null);
}

if (!$this->io->isInteractive()) {
$discardChanges = $this->config->get('discard-changes');
if (true === $discardChanges) {
return $this->discardChanges($path);
}
if ('stash' === $discardChanges) {
if (!$update) {
return parent::cleanChanges($package, $path, $update);
}

return $this->stashChanges($path);
}

return parent::cleanChanges($package, $path, $update);
}

$changes = array_map(static function ($elem): string {
return '    '.$elem;
}, Preg::split('{\s*\r?\n\s*}', $changes));
$this->io->writeError('    <error>'.$package->getPrettyName().' has modified files:</error>');
$this->io->writeError(array_slice($changes, 0, 10));
if (count($changes) > 10) {
$this->io->writeError('    <info>' . (count($changes) - 10) . ' more files modified, choose "v" to view the full list</info>');
}

while (true) {
switch ($this->io->ask('    <info>Discard changes [y,n,v,d,'.($update ? 's,' : '').'?]?</info> ', '?')) {
case 'y':
$this->discardChanges($path);
break 2;

case 's':
if (!$update) {
goto help;
}

$this->stashChanges($path);
break 2;

case 'n':
throw new \RuntimeException('Update aborted');

case 'v':
$this->io->writeError($changes);
break;

case 'd':
$this->viewDiff($path);
break;

case '?':
default:
help :
$this->io->writeError([
'    y - discard changes and apply the '.($update ? 'update' : 'uninstall'),
'    n - abort the '.($update ? 'update' : 'uninstall').' and let you manually clean things up',
'    v - view modified files',
'    d - view local modifications (diff)',
]);
if ($update) {
$this->io->writeError('    s - stash changes and try to reapply them after the update');
}
$this->io->writeError('    ? - print help');
break;
}
}

return \React\Promise\resolve(null);
}




protected function reapplyChanges(string $path): void
{
$path = $this->normalizePath($path);
if (!empty($this->hasStashedChanges[$path])) {
unset($this->hasStashedChanges[$path]);
$this->io->writeError('    <info>Re-applying stashed changes</info>');
if (0 !== $this->process->execute('git stash pop', $output, $path)) {
throw new \RuntimeException("Failed to apply stashed changes:\n\n".$this->process->getErrorOutput());
}
}

unset($this->hasDiscardedChanges[$path]);
}







protected function updateToCommit(PackageInterface $package, string $path, string $reference, string $prettyVersion): ?string
{
$force = !empty($this->hasDiscardedChanges[$path]) || !empty($this->hasStashedChanges[$path]) ? '-f ' : '';






$template = 'git checkout '.$force.'%s -- && git reset --hard %1$s --';
$branch = Preg::replace('{(?:^dev-|(?:\.x)?-dev$)}i', '', $prettyVersion);

$branches = null;
if (0 === $this->process->execute('git branch -r', $output, $path)) {
$branches = $output;
}


$gitRef = $reference;
if (!Preg::isMatch('{^[a-f0-9]{40}$}', $reference)
&& null !== $branches
&& Preg::isMatch('{^\s+composer/'.preg_quote($reference).'$}m', $branches)
) {
$command = sprintf('git checkout '.$force.'-B %s %s -- && git reset --hard %2$s --', ProcessExecutor::escape($branch), ProcessExecutor::escape('composer/'.$reference));
if (0 === $this->process->execute($command, $output, $path)) {
return null;
}
}


if (Preg::isMatch('{^[a-f0-9]{40}$}', $reference)) {

if (null !== $branches && !Preg::isMatch('{^\s+composer/'.preg_quote($branch).'$}m', $branches) && Preg::isMatch('{^\s+composer/v'.preg_quote($branch).'$}m', $branches)) {
$branch = 'v' . $branch;
}

$command = sprintf('git checkout %s --', ProcessExecutor::escape($branch));
$fallbackCommand = sprintf('git checkout '.$force.'-B %s %s --', ProcessExecutor::escape($branch), ProcessExecutor::escape('composer/'.$branch));
$resetCommand = sprintf('git reset --hard %s --', ProcessExecutor::escape($reference));

if (0 === $this->process->execute("($command || $fallbackCommand) && $resetCommand", $output, $path)) {
return null;
}
}

$command = sprintf($template, ProcessExecutor::escape($gitRef));
if (0 === $this->process->execute($command, $output, $path)) {
return null;
}

$exceptionExtra = '';


if (false !== strpos($this->process->getErrorOutput(), $reference)) {
$this->io->writeError('    <warning>'.$reference.' is gone (history was rewritten?)</warning>');
$exceptionExtra = "\nIt looks like the commit hash is not available in the repository, maybe ".($package->isDev() ? 'the commit was removed from the branch' : 'the tag was recreated').'? Run "composer update '.$package->getPrettyName().'" to resolve this.';
}

throw new \RuntimeException(Url::sanitize('Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput() . $exceptionExtra));
}

protected function updateOriginUrl(string $path, string $url): void
{
$this->process->execute(sprintf('git remote set-url origin -- %s', ProcessExecutor::escape($url)), $output, $path);
$this->setPushUrl($path, $url);
}

protected function setPushUrl(string $path, string $url): void
{

if (Preg::isMatch('{^(?:https?|git)://'.GitUtil::getGitHubDomainsRegex($this->config).'/([^/]+)/([^/]+?)(?:\.git)?$}', $url, $match)) {
$protocols = $this->config->get('github-protocols');
$pushUrl = 'git@'.$match[1].':'.$match[2].'/'.$match[3].'.git';
if (!in_array('ssh', $protocols, true)) {
$pushUrl = 'https://' . $match[1] . '/'.$match[2].'/'.$match[3].'.git';
}
$cmd = sprintf('git remote set-url --push origin -- %s', ProcessExecutor::escape($pushUrl));
$this->process->execute($cmd, $ignoredOutput, $path);
}
}




protected function getCommitLogs(string $fromReference, string $toReference, string $path): string
{
$path = $this->normalizePath($path);
$command = sprintf('git log %s..%s --pretty=format:"%%h - %%an: %%s"'.GitUtil::getNoShowSignatureFlag($this->process), ProcessExecutor::escape($fromReference), ProcessExecutor::escape($toReference));

if (0 !== $this->process->execute($command, $output, $path)) {
throw new \RuntimeException('Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput());
}

return $output;
}




protected function discardChanges(string $path): PromiseInterface
{
$path = $this->normalizePath($path);
if (0 !== $this->process->execute('git clean -df && git reset --hard', $output, $path)) {
throw new \RuntimeException("Could not reset changes\n\n:".$output);
}

$this->hasDiscardedChanges[$path] = true;

return \React\Promise\resolve(null);
}




protected function stashChanges(string $path): PromiseInterface
{
$path = $this->normalizePath($path);
if (0 !== $this->process->execute('git stash --include-untracked', $output, $path)) {
throw new \RuntimeException("Could not stash changes\n\n:".$output);
}

$this->hasStashedChanges[$path] = true;

return \React\Promise\resolve(null);
}




protected function viewDiff(string $path): void
{
$path = $this->normalizePath($path);
if (0 !== $this->process->execute('git diff HEAD', $output, $path)) {
throw new \RuntimeException("Could not view diff\n\n:".$output);
}

$this->io->writeError($output);
}

protected function normalizePath(string $path): string
{
if (Platform::isWindows() && strlen($path) > 0) {
$basePath = $path;
$removed = [];

while (!is_dir($basePath) && $basePath !== '\\') {
array_unshift($removed, basename($basePath));
$basePath = dirname($basePath);
}

if ($basePath === '\\') {
return $path;
}

$path = rtrim(realpath($basePath) . '/' . implode('/', $removed), '/');
}

return $path;
}




protected function hasMetadataRepository(string $path): bool
{
$path = $this->normalizePath($path);

return is_dir($path.'/.git');
}

protected function getShortHash(string $reference): string
{
if (!$this->io->isVerbose() && Preg::isMatch('{^[0-9a-f]{40}$}', $reference)) {
return substr($reference, 0, 10);
}

return $reference;
}
}
