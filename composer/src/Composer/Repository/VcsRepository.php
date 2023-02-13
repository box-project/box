<?php declare(strict_types=1);











namespace Composer\Repository;

use Composer\Downloader\TransportException;
use Composer\Pcre\Preg;
use Composer\Repository\Vcs\VcsDriverInterface;
use Composer\Package\Version\VersionParser;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Loader\ValidatingArrayLoader;
use Composer\Package\Loader\InvalidPackageException;
use Composer\Package\Loader\LoaderInterface;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Util\ProcessExecutor;
use Composer\Util\HttpDownloader;
use Composer\Util\Url;
use Composer\Semver\Constraint\Constraint;
use Composer\IO\IOInterface;
use Composer\Config;




class VcsRepository extends ArrayRepository implements ConfigurableRepositoryInterface
{

protected $url;

protected $packageName;

protected $isVerbose;

protected $isVeryVerbose;

protected $io;

protected $config;

protected $versionParser;

protected $type;

protected $loader;

protected $repoConfig;

protected $httpDownloader;

protected $processExecutor;

protected $branchErrorOccurred = false;

private $drivers;

private $driver;

private $versionCache;

private $emptyReferences = [];

private $versionTransportExceptions = [];





public function __construct(array $repoConfig, IOInterface $io, Config $config, HttpDownloader $httpDownloader, ?EventDispatcher $dispatcher = null, ?ProcessExecutor $process = null, ?array $drivers = null, ?VersionCacheInterface $versionCache = null)
{
parent::__construct();
$this->drivers = $drivers ?: [
'github' => 'Composer\Repository\Vcs\GitHubDriver',
'gitlab' => 'Composer\Repository\Vcs\GitLabDriver',
'bitbucket' => 'Composer\Repository\Vcs\GitBitbucketDriver',
'git-bitbucket' => 'Composer\Repository\Vcs\GitBitbucketDriver',
'git' => 'Composer\Repository\Vcs\GitDriver',
'hg' => 'Composer\Repository\Vcs\HgDriver',
'perforce' => 'Composer\Repository\Vcs\PerforceDriver',
'fossil' => 'Composer\Repository\Vcs\FossilDriver',

'svn' => 'Composer\Repository\Vcs\SvnDriver',
];

$this->url = $repoConfig['url'];
$this->io = $io;
$this->type = $repoConfig['type'] ?? 'vcs';
$this->isVerbose = $io->isVerbose();
$this->isVeryVerbose = $io->isVeryVerbose();
$this->config = $config;
$this->repoConfig = $repoConfig;
$this->versionCache = $versionCache;
$this->httpDownloader = $httpDownloader;
$this->processExecutor = $process ?? new ProcessExecutor($io);
}

public function getRepoName()
{
$driverClass = get_class($this->getDriver());
$driverType = array_search($driverClass, $this->drivers);
if (!$driverType) {
$driverType = $driverClass;
}

return 'vcs repo ('.$driverType.' '.Url::sanitize($this->url).')';
}

public function getRepoConfig()
{
return $this->repoConfig;
}

public function setLoader(LoaderInterface $loader): void
{
$this->loader = $loader;
}

public function getDriver(): ?VcsDriverInterface
{
if ($this->driver) {
return $this->driver;
}

if (isset($this->drivers[$this->type])) {
$class = $this->drivers[$this->type];
$this->driver = new $class($this->repoConfig, $this->io, $this->config, $this->httpDownloader, $this->processExecutor);
$this->driver->initialize();

return $this->driver;
}

foreach ($this->drivers as $driver) {
if ($driver::supports($this->io, $this->config, $this->url)) {
$this->driver = new $driver($this->repoConfig, $this->io, $this->config, $this->httpDownloader, $this->processExecutor);
$this->driver->initialize();

return $this->driver;
}
}

foreach ($this->drivers as $driver) {
if ($driver::supports($this->io, $this->config, $this->url, true)) {
$this->driver = new $driver($this->repoConfig, $this->io, $this->config, $this->httpDownloader, $this->processExecutor);
$this->driver->initialize();

return $this->driver;
}
}

return null;
}

public function hadInvalidBranches(): bool
{
return $this->branchErrorOccurred;
}




public function getEmptyReferences(): array
{
return $this->emptyReferences;
}




public function getVersionTransportExceptions(): array
{
return $this->versionTransportExceptions;
}

protected function initialize()
{
parent::initialize();

$isVerbose = $this->isVerbose;
$isVeryVerbose = $this->isVeryVerbose;

$driver = $this->getDriver();
if (!$driver) {
throw new \InvalidArgumentException('No driver found to handle VCS repository '.$this->url);
}

$this->versionParser = new VersionParser;
if (!$this->loader) {
$this->loader = new ArrayLoader($this->versionParser);
}

$hasRootIdentifierComposerJson = false;
try {
$hasRootIdentifierComposerJson = $driver->hasComposerFile($driver->getRootIdentifier());
if ($hasRootIdentifierComposerJson) {
$data = $driver->getComposerInformation($driver->getRootIdentifier());
$this->packageName = !empty($data['name']) ? $data['name'] : null;
}
} catch (\Exception $e) {
if ($e instanceof TransportException && $this->shouldRethrowTransportException($e)) {
throw $e;
}

if ($isVeryVerbose) {
$this->io->writeError('<error>Skipped parsing '.$driver->getRootIdentifier().', '.$e->getMessage().'</error>');
}
}

foreach ($driver->getTags() as $tag => $identifier) {
$tag = (string) $tag;
$msg = 'Reading composer.json of <info>' . ($this->packageName ?: $this->url) . '</info> (<comment>' . $tag . '</comment>)';
if ($isVeryVerbose) {
$this->io->writeError($msg);
} elseif ($isVerbose) {
$this->io->overwriteError($msg, false);
}


$tag = str_replace('release-', '', $tag);

$cachedPackage = $this->getCachedPackageVersion($tag, $identifier, $isVerbose, $isVeryVerbose);
if ($cachedPackage) {
$this->addPackage($cachedPackage);

continue;
}
if ($cachedPackage === false) {
$this->emptyReferences[] = $identifier;

continue;
}

if (!$parsedTag = $this->validateTag($tag)) {
if ($isVeryVerbose) {
$this->io->writeError('<warning>Skipped tag '.$tag.', invalid tag name</warning>');
}
continue;
}

try {
$data = $driver->getComposerInformation($identifier);
if (null === $data) {
if ($isVeryVerbose) {
$this->io->writeError('<warning>Skipped tag '.$tag.', no composer file</warning>');
}
$this->emptyReferences[] = $identifier;
continue;
}


if (isset($data['version'])) {
$data['version_normalized'] = $this->versionParser->normalize($data['version']);
} else {

$data['version'] = $tag;
$data['version_normalized'] = $parsedTag;
}


$data['version'] = Preg::replace('{[.-]?dev$}i', '', $data['version']);
$data['version_normalized'] = Preg::replace('{(^dev-|[.-]?dev$)}i', '', $data['version_normalized']);


unset($data['default-branch']);


if ($data['version_normalized'] !== $parsedTag) {
if ($isVeryVerbose) {
if (Preg::isMatch('{(^dev-|[.-]?dev$)}i', $parsedTag)) {
$this->io->writeError('<warning>Skipped tag '.$tag.', invalid tag name, tags can not use dev prefixes or suffixes</warning>');
} else {
$this->io->writeError('<warning>Skipped tag '.$tag.', tag ('.$parsedTag.') does not match version ('.$data['version_normalized'].') in composer.json</warning>');
}
}
continue;
}

$tagPackageName = $this->packageName ?: ($data['name'] ?? '');
if ($existingPackage = $this->findPackage($tagPackageName, $data['version_normalized'])) {
if ($isVeryVerbose) {
$this->io->writeError('<warning>Skipped tag '.$tag.', it conflicts with an another tag ('.$existingPackage->getPrettyVersion().') as both resolve to '.$data['version_normalized'].' internally</warning>');
}
continue;
}

if ($isVeryVerbose) {
$this->io->writeError('Importing tag '.$tag.' ('.$data['version_normalized'].')');
}

$this->addPackage($this->loader->load($this->preProcess($driver, $data, $identifier)));
} catch (\Exception $e) {
if ($e instanceof TransportException) {
$this->versionTransportExceptions['tags'][$tag] = $e;
if ($e->getCode() === 404) {
$this->emptyReferences[] = $identifier;
}
if ($this->shouldRethrowTransportException($e)) {
throw $e;
}
}
if ($isVeryVerbose) {
$this->io->writeError('<warning>Skipped tag '.$tag.', '.($e instanceof TransportException ? 'no composer file was found (' . $e->getCode() . ' HTTP status code)' : $e->getMessage()).'</warning>');
}
continue;
}
}

if (!$isVeryVerbose) {
$this->io->overwriteError('', false);
}

$branches = $driver->getBranches();

if ($hasRootIdentifierComposerJson && isset($branches[$driver->getRootIdentifier()])) {
$branches = [$driver->getRootIdentifier() => $branches[$driver->getRootIdentifier()]] + $branches;
}

foreach ($branches as $branch => $identifier) {
$branch = (string) $branch;
$msg = 'Reading composer.json of <info>' . ($this->packageName ?: $this->url) . '</info> (<comment>' . $branch . '</comment>)';
if ($isVeryVerbose) {
$this->io->writeError($msg);
} elseif ($isVerbose) {
$this->io->overwriteError($msg, false);
}

if (!$parsedBranch = $this->validateBranch($branch)) {
if ($isVeryVerbose) {
$this->io->writeError('<warning>Skipped branch '.$branch.', invalid name</warning>');
}
continue;
}


if (strpos($parsedBranch, 'dev-') === 0 || VersionParser::DEFAULT_BRANCH_ALIAS === $parsedBranch) {
$version = 'dev-' . $branch;
} else {
$prefix = strpos($branch, 'v') === 0 ? 'v' : '';
$version = $prefix . Preg::replace('{(\.9{7})+}', '.x', $parsedBranch);
}

$cachedPackage = $this->getCachedPackageVersion($version, $identifier, $isVerbose, $isVeryVerbose, $driver->getRootIdentifier() === $branch);
if ($cachedPackage) {
$this->addPackage($cachedPackage);

continue;
}
if ($cachedPackage === false) {
$this->emptyReferences[] = $identifier;

continue;
}

try {
$data = $driver->getComposerInformation($identifier);
if (null === $data) {
if ($isVeryVerbose) {
$this->io->writeError('<warning>Skipped branch '.$branch.', no composer file</warning>');
}
$this->emptyReferences[] = $identifier;
continue;
}


$data['version'] = $version;
$data['version_normalized'] = $parsedBranch;

unset($data['default-branch']);
if ($driver->getRootIdentifier() === $branch) {
$data['default-branch'] = true;
}

if ($isVeryVerbose) {
$this->io->writeError('Importing branch '.$branch.' ('.$data['version'].')');
}

$packageData = $this->preProcess($driver, $data, $identifier);
$package = $this->loader->load($packageData);
if ($this->loader instanceof ValidatingArrayLoader && \count($this->loader->getWarnings()) > 0) {
throw new InvalidPackageException($this->loader->getErrors(), $this->loader->getWarnings(), $packageData);
}
$this->addPackage($package);
} catch (TransportException $e) {
$this->versionTransportExceptions['branches'][$branch] = $e;
if ($e->getCode() === 404) {
$this->emptyReferences[] = $identifier;
}
if ($this->shouldRethrowTransportException($e)) {
throw $e;
}
if ($isVeryVerbose) {
$this->io->writeError('<warning>Skipped branch '.$branch.', no composer file was found (' . $e->getCode() . ' HTTP status code)</warning>');
}
continue;
} catch (\Exception $e) {
if (!$isVeryVerbose) {
$this->io->writeError('');
}
$this->branchErrorOccurred = true;
$this->io->writeError('<error>Skipped branch '.$branch.', '.$e->getMessage().'</error>');
$this->io->writeError('');
continue;
}
}
$driver->cleanup();

if (!$isVeryVerbose) {
$this->io->overwriteError('', false);
}

if (!$this->getPackages()) {
throw new InvalidRepositoryException('No valid composer.json was found in any branch or tag of '.$this->url.', could not load a package from it.');
}
}






protected function preProcess(VcsDriverInterface $driver, array $data, string $identifier): array
{



$dataPackageName = $data['name'] ?? null;
$data['name'] = $this->packageName ?: $dataPackageName;

if (!isset($data['dist'])) {
$data['dist'] = $driver->getDist($identifier);
}
if (!isset($data['source'])) {
$data['source'] = $driver->getSource($identifier);
}

return $data;
}




private function validateBranch(string $branch)
{
try {
$normalizedBranch = $this->versionParser->normalizeBranch($branch);


$this->versionParser->parseConstraints($normalizedBranch);

return $normalizedBranch;
} catch (\Exception $e) {
}

return false;
}




private function validateTag(string $version)
{
try {
return $this->versionParser->normalize($version);
} catch (\Exception $e) {
}

return false;
}




private function getCachedPackageVersion(string $version, string $identifier, bool $isVerbose, bool $isVeryVerbose, bool $isDefaultBranch = false)
{
if (!$this->versionCache) {
return null;
}

$cachedPackage = $this->versionCache->getVersionPackage($version, $identifier);
if ($cachedPackage === false) {
if ($isVeryVerbose) {
$this->io->writeError('<warning>Skipped '.$version.', no composer file (cached from ref '.$identifier.')</warning>');
}

return false;
}

if ($cachedPackage) {
$msg = 'Found cached composer.json of <info>' . ($this->packageName ?: $this->url) . '</info> (<comment>' . $version . '</comment>)';
if ($isVeryVerbose) {
$this->io->writeError($msg);
} elseif ($isVerbose) {
$this->io->overwriteError($msg, false);
}

unset($cachedPackage['default-branch']);
if ($isDefaultBranch) {
$cachedPackage['default-branch'] = true;
}

if ($existingPackage = $this->findPackage($cachedPackage['name'], new Constraint('=', $cachedPackage['version_normalized']))) {
if ($isVeryVerbose) {
$this->io->writeError('<warning>Skipped cached version '.$version.', it conflicts with an another tag ('.$existingPackage->getPrettyVersion().') as both resolve to '.$cachedPackage['version_normalized'].' internally</warning>');
}
$cachedPackage = null;
}
}

if ($cachedPackage) {
return $this->loader->load($cachedPackage);
}

return null;
}

private function shouldRethrowTransportException(TransportException $e): bool
{
return in_array($e->getCode(), [401, 403, 429], true) || $e->getCode() >= 500;
}
}
