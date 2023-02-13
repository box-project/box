<?php declare(strict_types=1);











namespace Composer;

use Composer\Autoload\AutoloadGenerator;
use Composer\Console\GithubActionError;
use Composer\DependencyResolver\DefaultPolicy;
use Composer\DependencyResolver\LocalRepoTransaction;
use Composer\DependencyResolver\LockTransaction;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\PoolOptimizer;
use Composer\DependencyResolver\Pool;
use Composer\DependencyResolver\Request;
use Composer\DependencyResolver\Solver;
use Composer\DependencyResolver\SolverProblemsException;
use Composer\DependencyResolver\PolicyInterface;
use Composer\Downloader\DownloadManager;
use Composer\Downloader\TransportException;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Filter\PlatformRequirementFilter\IgnoreListPlatformRequirementFilter;
use Composer\Filter\PlatformRequirementFilter\PlatformRequirementFilterFactory;
use Composer\Filter\PlatformRequirementFilter\PlatformRequirementFilterInterface;
use Composer\Installer\InstallationManager;
use Composer\Installer\InstallerEvents;
use Composer\Installer\SuggestedPackagesReporter;
use Composer\IO\IOInterface;
use Composer\Package\AliasPackage;
use Composer\Package\RootAliasPackage;
use Composer\Package\BasePackage;
use Composer\Package\CompletePackage;
use Composer\Package\CompletePackageInterface;
use Composer\Package\Link;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Dumper\ArrayDumper;
use Composer\Package\Version\VersionParser;
use Composer\Package\Package;
use Composer\Repository\ArrayRepository;
use Composer\Repository\RepositorySet;
use Composer\Repository\CompositeRepository;
use Composer\Semver\Constraint\Constraint;
use Composer\Package\Locker;
use Composer\Package\RootPackageInterface;
use Composer\Repository\InstalledArrayRepository;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\InstalledRepository;
use Composer\Repository\RootPackageRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Repository\LockArrayRepository;
use Composer\Script\ScriptEvents;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Advisory\Auditor;
use Composer\Util\Platform;







class Installer
{
public const ERROR_NONE = 0; 
public const ERROR_GENERIC_FAILURE = 1;
public const ERROR_NO_LOCK_FILE_FOR_PARTIAL_UPDATE = 3;
public const ERROR_LOCK_FILE_INVALID = 4;

public const ERROR_DEPENDENCY_RESOLUTION_FAILED = 2;




protected $io;




protected $config;




protected $package;





protected $fixedRootPackage;




protected $downloadManager;




protected $repositoryManager;




protected $locker;




protected $installationManager;




protected $eventDispatcher;




protected $autoloadGenerator;


protected $preferSource = false;

protected $preferDist = false;

protected $optimizeAutoloader = false;

protected $classMapAuthoritative = false;

protected $apcuAutoloader = false;

protected $apcuAutoloaderPrefix = null;

protected $devMode = false;

protected $dryRun = false;

protected $downloadOnly = false;

protected $verbose = false;

protected $update = false;

protected $install = true;

protected $dumpAutoloader = true;

protected $runScripts = true;

protected $preferStable = false;

protected $preferLowest = false;

protected $writeLock;

protected $executeOperations = true;

protected $audit = true;

protected $auditFormat = Auditor::FORMAT_SUMMARY;


protected $updateMirrors = false;





protected $updateAllowList = null;

protected $updateAllowTransitiveDependencies = Request::UPDATE_ONLY_LISTED;




protected $suggestedPackagesReporter;




protected $platformRequirementFilter;




protected $additionalFixedRepository;


protected $temporaryConstraints = [];






public function __construct(IOInterface $io, Config $config, RootPackageInterface $package, DownloadManager $downloadManager, RepositoryManager $repositoryManager, Locker $locker, InstallationManager $installationManager, EventDispatcher $eventDispatcher, AutoloadGenerator $autoloadGenerator)
{
$this->io = $io;
$this->config = $config;
$this->package = $package;
$this->downloadManager = $downloadManager;
$this->repositoryManager = $repositoryManager;
$this->locker = $locker;
$this->installationManager = $installationManager;
$this->eventDispatcher = $eventDispatcher;
$this->autoloadGenerator = $autoloadGenerator;
$this->suggestedPackagesReporter = new SuggestedPackagesReporter($this->io);
$this->platformRequirementFilter = PlatformRequirementFilterFactory::ignoreNothing();

$this->writeLock = $config->get('lock');
}








public function run(): int
{




gc_collect_cycles();
gc_disable();

if ($this->updateAllowList && $this->updateMirrors) {
throw new \RuntimeException("The installer options updateMirrors and updateAllowList are mutually exclusive.");
}

$isFreshInstall = $this->repositoryManager->getLocalRepository()->isFresh();


if (!$this->update && !$this->locker->isLocked()) {
$this->io->writeError('<warning>No composer.lock file present. Updating dependencies to latest instead of installing from lock file. See https://getcomposer.org/install for more information.</warning>');
$this->update = true;
}

if ($this->dryRun) {
$this->verbose = true;
$this->runScripts = false;
$this->executeOperations = false;
$this->writeLock = false;
$this->dumpAutoloader = false;
$this->mockLocalRepositories($this->repositoryManager);
}

if ($this->downloadOnly) {
$this->dumpAutoloader = false;
}

if ($this->update && !$this->install) {
$this->dumpAutoloader = false;
}

if ($this->runScripts) {
Platform::putEnv('COMPOSER_DEV_MODE', $this->devMode ? '1' : '0');



$eventName = $this->update ? ScriptEvents::PRE_UPDATE_CMD : ScriptEvents::PRE_INSTALL_CMD;
$this->eventDispatcher->dispatchScript($eventName, $this->devMode);
}

$this->downloadManager->setPreferSource($this->preferSource);
$this->downloadManager->setPreferDist($this->preferDist);

$localRepo = $this->repositoryManager->getLocalRepository();

try {
if ($this->update) {
$res = $this->doUpdate($localRepo, $this->install);
} else {
$res = $this->doInstall($localRepo);
}
if ($res !== 0) {
return $res;
}
} catch (\Exception $e) {
if ($this->executeOperations && $this->install && $this->config->get('notify-on-install')) {
$this->installationManager->notifyInstalls($this->io);
}

throw $e;
}
if ($this->executeOperations && $this->install && $this->config->get('notify-on-install')) {
$this->installationManager->notifyInstalls($this->io);
}

if ($this->update) {
$installedRepo = new InstalledRepository([
$this->locker->getLockedRepository($this->devMode),
$this->createPlatformRepo(false),
new RootPackageRepository(clone $this->package),
]);
if ($isFreshInstall) {
$this->suggestedPackagesReporter->addSuggestionsFromPackage($this->package);
}
$this->suggestedPackagesReporter->outputMinimalistic($installedRepo);
}


$lockedRepository = $this->locker->getLockedRepository(true);
foreach ($lockedRepository->getPackages() as $package) {
if (!$package instanceof CompletePackage || !$package->isAbandoned()) {
continue;
}

$replacement = is_string($package->getReplacementPackage())
? 'Use ' . $package->getReplacementPackage() . ' instead'
: 'No replacement was suggested';

$this->io->writeError(
sprintf(
"<warning>Package %s is abandoned, you should avoid using it. %s.</warning>",
$package->getPrettyName(),
$replacement
)
);
}

if ($this->dumpAutoloader) {

if ($this->optimizeAutoloader) {
$this->io->writeError('<info>Generating optimized autoload files</info>');
} else {
$this->io->writeError('<info>Generating autoload files</info>');
}

$this->autoloadGenerator->setClassMapAuthoritative($this->classMapAuthoritative);
$this->autoloadGenerator->setApcu($this->apcuAutoloader, $this->apcuAutoloaderPrefix);
$this->autoloadGenerator->setRunScripts($this->runScripts);
$this->autoloadGenerator->setPlatformRequirementFilter($this->platformRequirementFilter);
$this->autoloadGenerator->dump($this->config, $localRepo, $this->package, $this->installationManager, 'composer', $this->optimizeAutoloader);
}

if ($this->install && $this->executeOperations) {

foreach ($localRepo->getPackages() as $package) {
$this->installationManager->ensureBinariesPresence($package);
}
}

$fundingCount = 0;
foreach ($localRepo->getPackages() as $package) {
if ($package instanceof CompletePackageInterface && !$package instanceof AliasPackage && $package->getFunding()) {
$fundingCount++;
}
}
if ($fundingCount > 0) {
$this->io->writeError([
sprintf(
"<info>%d package%s you are using %s looking for funding.</info>",
$fundingCount,
1 === $fundingCount ? '' : 's',
1 === $fundingCount ? 'is' : 'are'
),
'<info>Use the `composer fund` command to find out more!</info>',
]);
}

if ($this->runScripts) {

$eventName = $this->update ? ScriptEvents::POST_UPDATE_CMD : ScriptEvents::POST_INSTALL_CMD;
$this->eventDispatcher->dispatchScript($eventName, $this->devMode);
}


if (!defined('HHVM_VERSION')) {
gc_enable();
}

if ($this->audit) {
if ($this->update && !$this->install) {
$packages = $lockedRepository->getCanonicalPackages();
$target = 'locked';
} else {
$packages = $localRepo->getCanonicalPackages();
$target = 'installed';
}
if (count($packages) > 0) {
try {
$auditor = new Auditor();
$repoSet = new RepositorySet();
foreach ($this->repositoryManager->getRepositories() as $repo) {
$repoSet->addRepository($repo);
}
$auditor->audit($this->io, $repoSet, $packages, $this->auditFormat);
} catch (TransportException $e) {
$this->io->error('Failed to audit '.$target.' packages.');
if ($this->io->isVerbose()) {
$this->io->error('['.get_class($e).'] '.$e->getMessage());
}
}
} else {
$this->io->writeError('No '.$target.' packages - skipping audit.');
}
}

return 0;
}




protected function doUpdate(InstalledRepositoryInterface $localRepo, bool $doInstall): int
{
$platformRepo = $this->createPlatformRepo(true);
$aliases = $this->getRootAliases(true);

$lockedRepository = null;

try {
if ($this->locker->isLocked()) {
$lockedRepository = $this->locker->getLockedRepository(true);
}
} catch (\Seld\JsonLint\ParsingException $e) {
if ($this->updateAllowList || $this->updateMirrors) {

throw $e;
}


}

if (($this->updateAllowList || $this->updateMirrors) && !$lockedRepository) {
$this->io->writeError('<error>Cannot update ' . ($this->updateMirrors ? 'lock file information' : 'only a partial set of packages') . ' without a lock file present. Run `composer update` to generate a lock file.</error>', true, IOInterface::QUIET);

return self::ERROR_NO_LOCK_FILE_FOR_PARTIAL_UPDATE;
}

$this->io->writeError('<info>Loading composer repositories with package information</info>');


$policy = $this->createPolicy(true);
$repositorySet = $this->createRepositorySet(true, $platformRepo, $aliases);
$repositories = $this->repositoryManager->getRepositories();
foreach ($repositories as $repository) {
$repositorySet->addRepository($repository);
}
if ($lockedRepository) {
$repositorySet->addRepository($lockedRepository);
}

$request = $this->createRequest($this->fixedRootPackage, $platformRepo, $lockedRepository);
$this->requirePackagesForUpdate($request, $lockedRepository, true);


if ($this->updateAllowList) {
$request->setUpdateAllowList($this->updateAllowList, $this->updateAllowTransitiveDependencies);
}

$pool = $repositorySet->createPool($request, $this->io, $this->eventDispatcher, $this->createPoolOptimizer($policy));

$this->io->writeError('<info>Updating dependencies</info>');


$solver = new Solver($policy, $pool, $this->io);
try {
$lockTransaction = $solver->solve($request, $this->platformRequirementFilter);
$ruleSetSize = $solver->getRuleSetSize();
$solver = null;
} catch (SolverProblemsException $e) {
$err = 'Your requirements could not be resolved to an installable set of packages.';
$prettyProblem = $e->getPrettyString($repositorySet, $request, $pool, $this->io->isVerbose());

$this->io->writeError('<error>'. $err .'</error>', true, IOInterface::QUIET);
$this->io->writeError($prettyProblem);
if (!$this->devMode) {
$this->io->writeError('<warning>Running update with --no-dev does not mean require-dev is ignored, it just means the packages will not be installed. If dev requirements are blocking the update you have to resolve those problems.</warning>', true, IOInterface::QUIET);
}

$ghe = new GithubActionError($this->io);
$ghe->emit($err."\n".$prettyProblem);

return max(self::ERROR_GENERIC_FAILURE, $e->getCode());
}

$this->io->writeError("Analyzed ".count($pool)." packages to resolve dependencies", true, IOInterface::VERBOSE);
$this->io->writeError("Analyzed ".$ruleSetSize." rules to resolve dependencies", true, IOInterface::VERBOSE);

$pool = null;

if (!$lockTransaction->getOperations()) {
$this->io->writeError('Nothing to modify in lock file');
}

$exitCode = $this->extractDevPackages($lockTransaction, $platformRepo, $aliases, $policy, $lockedRepository);
if ($exitCode !== 0) {
return $exitCode;
}


if (method_exists('Composer\Semver\CompilingMatcher', 'clear')) { 
\Composer\Semver\CompilingMatcher::clear();
}


$platformReqs = $this->extractPlatformRequirements($this->package->getRequires());
$platformDevReqs = $this->extractPlatformRequirements($this->package->getDevRequires());

$installsUpdates = $uninstalls = [];
if ($lockTransaction->getOperations()) {
$installNames = $updateNames = $uninstallNames = [];
foreach ($lockTransaction->getOperations() as $operation) {
if ($operation instanceof InstallOperation) {
$installsUpdates[] = $operation;
$installNames[] = $operation->getPackage()->getPrettyName().':'.$operation->getPackage()->getFullPrettyVersion();
} elseif ($operation instanceof UpdateOperation) {


if ($this->updateMirrors
&& $operation->getInitialPackage()->getName() === $operation->getTargetPackage()->getName()
&& $operation->getInitialPackage()->getVersion() === $operation->getTargetPackage()->getVersion()
) {
continue;
}

$installsUpdates[] = $operation;
$updateNames[] = $operation->getTargetPackage()->getPrettyName().':'.$operation->getTargetPackage()->getFullPrettyVersion();
} elseif ($operation instanceof UninstallOperation) {
$uninstalls[] = $operation;
$uninstallNames[] = $operation->getPackage()->getPrettyName();
}
}

if ($this->config->get('lock')) {
$this->io->writeError(sprintf(
"<info>Lock file operations: %d install%s, %d update%s, %d removal%s</info>",
count($installNames),
1 === count($installNames) ? '' : 's',
count($updateNames),
1 === count($updateNames) ? '' : 's',
count($uninstalls),
1 === count($uninstalls) ? '' : 's'
));
if ($installNames) {
$this->io->writeError("Installs: ".implode(', ', $installNames), true, IOInterface::VERBOSE);
}
if ($updateNames) {
$this->io->writeError("Updates: ".implode(', ', $updateNames), true, IOInterface::VERBOSE);
}
if ($uninstalls) {
$this->io->writeError("Removals: ".implode(', ', $uninstallNames), true, IOInterface::VERBOSE);
}
}
}

$sortByName = static function ($a, $b): int {
if ($a instanceof UpdateOperation) {
$a = $a->getTargetPackage()->getName();
} else {
$a = $a->getPackage()->getName();
}
if ($b instanceof UpdateOperation) {
$b = $b->getTargetPackage()->getName();
} else {
$b = $b->getPackage()->getName();
}

return strcmp($a, $b);
};
usort($uninstalls, $sortByName);
usort($installsUpdates, $sortByName);

foreach (array_merge($uninstalls, $installsUpdates) as $operation) {

if ($operation instanceof InstallOperation) {
$this->suggestedPackagesReporter->addSuggestionsFromPackage($operation->getPackage());
}


if ($this->config->get('lock') && (false === strpos($operation->getOperationType(), 'Alias') || $this->io->isDebug())) {
$this->io->writeError('  - ' . $operation->show(true));
}
}

$updatedLock = $this->locker->setLockData(
$lockTransaction->getNewLockPackages(false, $this->updateMirrors),
$lockTransaction->getNewLockPackages(true, $this->updateMirrors),
$platformReqs,
$platformDevReqs,
$lockTransaction->getAliases($aliases),
$this->package->getMinimumStability(),
$this->package->getStabilityFlags(),
$this->preferStable || $this->package->getPreferStable(),
$this->preferLowest,
$this->config->get('platform') ?: [],
$this->writeLock && $this->executeOperations
);
if ($updatedLock && $this->writeLock && $this->executeOperations) {
$this->io->writeError('<info>Writing lock file</info>');
}


if ($this->executeOperations && count($lockTransaction->getOperations()) > 0) {
$vendorDir = $this->config->get('vendor-dir');
if (is_dir($vendorDir)) {


@touch($vendorDir);
}
}

if ($doInstall) {

return $this->doInstall($localRepo, true);
}

return 0;
}










protected function extractDevPackages(LockTransaction $lockTransaction, PlatformRepository $platformRepo, array $aliases, PolicyInterface $policy, ?LockArrayRepository $lockedRepository = null): int
{
if (!$this->package->getDevRequires()) {
return 0;
}

$resultRepo = new ArrayRepository([]);
$loader = new ArrayLoader(null, true);
$dumper = new ArrayDumper();
foreach ($lockTransaction->getNewLockPackages(false) as $pkg) {
$resultRepo->addPackage($loader->load($dumper->dump($pkg)));
}

$repositorySet = $this->createRepositorySet(true, $platformRepo, $aliases);
$repositorySet->addRepository($resultRepo);

$request = $this->createRequest($this->fixedRootPackage, $platformRepo);
$this->requirePackagesForUpdate($request, $lockedRepository, false);

$pool = $repositorySet->createPoolWithAllPackages();

$solver = new Solver($policy, $pool, $this->io);
try {
$nonDevLockTransaction = $solver->solve($request, $this->platformRequirementFilter);
$solver = null;
} catch (SolverProblemsException $e) {
$err = 'Unable to find a compatible set of packages based on your non-dev requirements alone.';
$prettyProblem = $e->getPrettyString($repositorySet, $request, $pool, $this->io->isVerbose(), true);

$this->io->writeError('<error>'. $err .'</error>', true, IOInterface::QUIET);
$this->io->writeError('Your requirements can be resolved successfully when require-dev packages are present.');
$this->io->writeError('You may need to move packages from require-dev or some of their dependencies to require.');
$this->io->writeError($prettyProblem);

$ghe = new GithubActionError($this->io);
$ghe->emit($err."\n".$prettyProblem);

return $e->getCode();
}

$lockTransaction->setNonDevPackages($nonDevLockTransaction);

return 0;
}






protected function doInstall(InstalledRepositoryInterface $localRepo, bool $alreadySolved = false): int
{
if ($this->config->get('lock')) {
$this->io->writeError('<info>Installing dependencies from lock file'.($this->devMode ? ' (including require-dev)' : '').'</info>');
}

$lockedRepository = $this->locker->getLockedRepository($this->devMode);



if (!$alreadySolved) {
$this->io->writeError('<info>Verifying lock file contents can be installed on current platform.</info>');

$platformRepo = $this->createPlatformRepo(false);

$policy = $this->createPolicy(false);

$repositorySet = $this->createRepositorySet(false, $platformRepo, [], $lockedRepository);
$repositorySet->addRepository($lockedRepository);


$request = $this->createRequest($this->fixedRootPackage, $platformRepo, $lockedRepository);

if (!$this->locker->isFresh()) {
$this->io->writeError('<warning>Warning: The lock file is not up to date with the latest changes in composer.json. You may be getting outdated dependencies. It is recommended that you run `composer update` or `composer update <package name>`.</warning>', true, IOInterface::QUIET);
}

$missingRequirementInfo = $this->locker->getMissingRequirementInfo($this->package, $this->devMode);
if ($missingRequirementInfo !== []) {
$this->io->writeError($missingRequirementInfo);

return self::ERROR_LOCK_FILE_INVALID;
}

foreach ($lockedRepository->getPackages() as $package) {
$request->fixLockedPackage($package);
}

foreach ($this->locker->getPlatformRequirements($this->devMode) as $link) {
$request->requireName($link->getTarget(), $link->getConstraint());
}

$pool = $repositorySet->createPool($request, $this->io, $this->eventDispatcher);


$solver = new Solver($policy, $pool, $this->io);
try {
$lockTransaction = $solver->solve($request, $this->platformRequirementFilter);
$solver = null;


if (0 !== count($lockTransaction->getOperations())) {
$this->io->writeError('<error>Your lock file cannot be installed on this system without changes. Please run composer update.</error>', true, IOInterface::QUIET);

return self::ERROR_LOCK_FILE_INVALID;
}
} catch (SolverProblemsException $e) {
$err = 'Your lock file does not contain a compatible set of packages. Please run composer update.';
$prettyProblem = $e->getPrettyString($repositorySet, $request, $pool, $this->io->isVerbose());

$this->io->writeError('<error>'. $err .'</error>', true, IOInterface::QUIET);
$this->io->writeError($prettyProblem);

$ghe = new GithubActionError($this->io);
$ghe->emit($err."\n".$prettyProblem);

return max(self::ERROR_GENERIC_FAILURE, $e->getCode());
}
}


$localRepoTransaction = new LocalRepoTransaction($lockedRepository, $localRepo);
$this->eventDispatcher->dispatchInstallerEvent(InstallerEvents::PRE_OPERATIONS_EXEC, $this->devMode, $this->executeOperations, $localRepoTransaction);

$installs = $updates = $uninstalls = [];
foreach ($localRepoTransaction->getOperations() as $operation) {
if ($operation instanceof InstallOperation) {
$installs[] = $operation->getPackage()->getPrettyName().':'.$operation->getPackage()->getFullPrettyVersion();
} elseif ($operation instanceof UpdateOperation) {
$updates[] = $operation->getTargetPackage()->getPrettyName().':'.$operation->getTargetPackage()->getFullPrettyVersion();
} elseif ($operation instanceof UninstallOperation) {
$uninstalls[] = $operation->getPackage()->getPrettyName();
}
}

if ($installs === [] && $updates === [] && $uninstalls === []) {
$this->io->writeError('Nothing to install, update or remove');
} else {
$this->io->writeError(sprintf(
"<info>Package operations: %d install%s, %d update%s, %d removal%s</info>",
count($installs),
1 === count($installs) ? '' : 's',
count($updates),
1 === count($updates) ? '' : 's',
count($uninstalls),
1 === count($uninstalls) ? '' : 's'
));
if ($installs) {
$this->io->writeError("Installs: ".implode(', ', $installs), true, IOInterface::VERBOSE);
}
if ($updates) {
$this->io->writeError("Updates: ".implode(', ', $updates), true, IOInterface::VERBOSE);
}
if ($uninstalls) {
$this->io->writeError("Removals: ".implode(', ', $uninstalls), true, IOInterface::VERBOSE);
}
}

if ($this->executeOperations) {
$localRepo->setDevPackageNames($this->locker->getDevPackageNames());
$this->installationManager->execute($localRepo, $localRepoTransaction->getOperations(), $this->devMode, $this->runScripts, $this->downloadOnly);
} else {
foreach ($localRepoTransaction->getOperations() as $operation) {

if (false === strpos($operation->getOperationType(), 'Alias') || $this->io->isDebug()) {
$this->io->writeError('  - ' . $operation->show(false));
}
}
}

return 0;
}

protected function createPlatformRepo(bool $forUpdate): PlatformRepository
{
if ($forUpdate) {
$platformOverrides = $this->config->get('platform') ?: [];
} else {
$platformOverrides = $this->locker->getPlatformOverrides();
}

return new PlatformRepository([], $platformOverrides);
}






private function createRepositorySet(bool $forUpdate, PlatformRepository $platformRepo, array $rootAliases = [], ?RepositoryInterface $lockedRepository = null): RepositorySet
{
if ($forUpdate) {
$minimumStability = $this->package->getMinimumStability();
$stabilityFlags = $this->package->getStabilityFlags();

$requires = array_merge($this->package->getRequires(), $this->package->getDevRequires());
} else {
$minimumStability = $this->locker->getMinimumStability();
$stabilityFlags = $this->locker->getStabilityFlags();

$requires = [];
foreach ($lockedRepository->getPackages() as $package) {
$constraint = new Constraint('=', $package->getVersion());
$constraint->setPrettyString($package->getPrettyVersion());
$requires[$package->getName()] = $constraint;
}
}

$rootRequires = [];
foreach ($requires as $req => $constraint) {
if ($constraint instanceof Link) {
$constraint = $constraint->getConstraint();
}

if ($this->platformRequirementFilter->isIgnored($req)) {
continue;
} elseif ($this->platformRequirementFilter instanceof IgnoreListPlatformRequirementFilter) {
$constraint = $this->platformRequirementFilter->filterConstraint($req, $constraint);
}
$rootRequires[$req] = $constraint;
}

$this->fixedRootPackage = clone $this->package;
$this->fixedRootPackage->setRequires([]);
$this->fixedRootPackage->setDevRequires([]);

$stabilityFlags[$this->package->getName()] = BasePackage::$stabilities[VersionParser::parseStability($this->package->getVersion())];

$repositorySet = new RepositorySet($minimumStability, $stabilityFlags, $rootAliases, $this->package->getReferences(), $rootRequires, $this->temporaryConstraints);
$repositorySet->addRepository(new RootPackageRepository($this->fixedRootPackage));
$repositorySet->addRepository($platformRepo);
if ($this->additionalFixedRepository) {


$additionalFixedRepositories = $this->additionalFixedRepository;
if ($additionalFixedRepositories instanceof CompositeRepository) {
$additionalFixedRepositories = $additionalFixedRepositories->getRepositories();
} else {
$additionalFixedRepositories = [$additionalFixedRepositories];
}
foreach ($additionalFixedRepositories as $additionalFixedRepository) {
if ($additionalFixedRepository instanceof InstalledRepository || $additionalFixedRepository instanceof InstalledRepositoryInterface) {
$repositorySet->allowInstalledRepositories();
break;
}
}

$repositorySet->addRepository($this->additionalFixedRepository);
}

return $repositorySet;
}

private function createPolicy(bool $forUpdate): DefaultPolicy
{
$preferStable = null;
$preferLowest = null;
if (!$forUpdate) {
$preferStable = $this->locker->getPreferStable();
$preferLowest = $this->locker->getPreferLowest();
}


if (null === $preferStable) {
$preferStable = $this->preferStable || $this->package->getPreferStable();
}
if (null === $preferLowest) {
$preferLowest = $this->preferLowest;
}

return new DefaultPolicy($preferStable, $preferLowest);
}




private function createRequest(RootPackageInterface $rootPackage, PlatformRepository $platformRepo, ?LockArrayRepository $lockedRepository = null): Request
{
$request = new Request($lockedRepository);

$request->fixPackage($rootPackage);
if ($rootPackage instanceof RootAliasPackage) {
$request->fixPackage($rootPackage->getAliasOf());
}

$fixedPackages = $platformRepo->getPackages();
if ($this->additionalFixedRepository) {
$fixedPackages = array_merge($fixedPackages, $this->additionalFixedRepository->getPackages());
}




$provided = $rootPackage->getProvides();
foreach ($fixedPackages as $package) {

if ($package->getRepository() !== $platformRepo
|| !isset($provided[$package->getName()])
|| !$provided[$package->getName()]->getConstraint()->matches(new Constraint('=', $package->getVersion()))
) {
$request->fixPackage($package);
}
}

return $request;
}

private function requirePackagesForUpdate(Request $request, ?LockArrayRepository $lockedRepository = null, bool $includeDevRequires = true): void
{

if ($this->updateMirrors) {
$excludedPackages = [];
if (!$includeDevRequires) {
$excludedPackages = array_flip($this->locker->getDevPackageNames());
}

foreach ($lockedRepository->getPackages() as $lockedPackage) {


if (!$lockedPackage instanceof AliasPackage && !isset($excludedPackages[$lockedPackage->getName()])) {
$request->requireName($lockedPackage->getName(), new Constraint('==', $lockedPackage->getVersion()));
}
}
} else {
$links = $this->package->getRequires();
if ($includeDevRequires) {
$links = array_merge($links, $this->package->getDevRequires());
}
foreach ($links as $link) {
$request->requireName($link->getTarget(), $link->getConstraint());
}
}
}






private function getRootAliases(bool $forUpdate): array
{
if ($forUpdate) {
$aliases = $this->package->getAliases();
} else {
$aliases = $this->locker->getAliases();
}

return $aliases;
}






private function extractPlatformRequirements(array $links): array
{
$platformReqs = [];
foreach ($links as $link) {
if (PlatformRepository::isPlatformPackage($link->getTarget())) {
$platformReqs[$link->getTarget()] = $link->getPrettyConstraint();
}
}

return $platformReqs;
}






private function mockLocalRepositories(RepositoryManager $rm): void
{
$packages = [];
foreach ($rm->getLocalRepository()->getPackages() as $package) {
$packages[(string) $package] = clone $package;
}
foreach ($packages as $key => $package) {
if ($package instanceof AliasPackage) {
$alias = (string) $package->getAliasOf();
$className = get_class($package);
$packages[$key] = new $className($packages[$alias], $package->getVersion(), $package->getPrettyVersion());
}
}
$rm->setLocalRepository(
new InstalledArrayRepository($packages)
);
}

private function createPoolOptimizer(PolicyInterface $policy): ?PoolOptimizer
{



if ('0' === Platform::getEnv('COMPOSER_POOL_OPTIMIZER')) {
$this->io->write('Pool Optimizer was disabled for debugging purposes.', true, IOInterface::DEBUG);

return null;
}

return new PoolOptimizer($policy);
}






public static function create(IOInterface $io, Composer $composer): self
{
return new static(
$io,
$composer->getConfig(),
$composer->getPackage(),
$composer->getDownloadManager(),
$composer->getRepositoryManager(),
$composer->getLocker(),
$composer->getInstallationManager(),
$composer->getEventDispatcher(),
$composer->getAutoloadGenerator()
);
}




public function setAdditionalFixedRepository(RepositoryInterface $additionalFixedRepository): self
{
$this->additionalFixedRepository = $additionalFixedRepository;

return $this;
}





public function setTemporaryConstraints(array $constraints): self
{
$this->temporaryConstraints = $constraints;

return $this;
}






public function setDryRun(bool $dryRun = true): self
{
$this->dryRun = (bool) $dryRun;

return $this;
}




public function isDryRun(): bool
{
return $this->dryRun;
}






public function setDownloadOnly(bool $downloadOnly = true): self
{
$this->downloadOnly = $downloadOnly;

return $this;
}






public function setPreferSource(bool $preferSource = true): self
{
$this->preferSource = (bool) $preferSource;

return $this;
}






public function setPreferDist(bool $preferDist = true): self
{
$this->preferDist = (bool) $preferDist;

return $this;
}






public function setOptimizeAutoloader(bool $optimizeAutoloader): self
{
$this->optimizeAutoloader = (bool) $optimizeAutoloader;
if (!$this->optimizeAutoloader) {


$this->setClassMapAuthoritative(false);
}

return $this;
}







public function setClassMapAuthoritative(bool $classMapAuthoritative): self
{
$this->classMapAuthoritative = (bool) $classMapAuthoritative;
if ($this->classMapAuthoritative) {

$this->setOptimizeAutoloader(true);
}

return $this;
}






public function setApcuAutoloader(bool $apcuAutoloader, ?string $apcuAutoloaderPrefix = null): self
{
$this->apcuAutoloader = $apcuAutoloader;
$this->apcuAutoloaderPrefix = $apcuAutoloaderPrefix;

return $this;
}






public function setUpdate(bool $update): self
{
$this->update = (bool) $update;

return $this;
}






public function setInstall(bool $install): self
{
$this->install = (bool) $install;

return $this;
}






public function setDevMode(bool $devMode = true): self
{
$this->devMode = (bool) $devMode;

return $this;
}








public function setDumpAutoloader(bool $dumpAutoloader = true): self
{
$this->dumpAutoloader = (bool) $dumpAutoloader;

return $this;
}









public function setRunScripts(bool $runScripts = true): self
{
$this->runScripts = (bool) $runScripts;

return $this;
}






public function setConfig(Config $config): self
{
$this->config = $config;

return $this;
}






public function setVerbose(bool $verbose = true): self
{
$this->verbose = (bool) $verbose;

return $this;
}




public function isVerbose(): bool
{
return $this->verbose;
}














public function setIgnorePlatformRequirements($ignorePlatformReqs): self
{
trigger_error('Installer::setIgnorePlatformRequirements is deprecated since Composer 2.2, use setPlatformRequirementFilter instead.', E_USER_DEPRECATED);

return $this->setPlatformRequirementFilter(PlatformRequirementFilterFactory::fromBoolOrList($ignorePlatformReqs));
}




public function setPlatformRequirementFilter(PlatformRequirementFilterInterface $platformRequirementFilter): self
{
$this->platformRequirementFilter = $platformRequirementFilter;

return $this;
}






public function setUpdateMirrors(bool $updateMirrors): self
{
$this->updateMirrors = $updateMirrors;

return $this;
}









public function setUpdateAllowList(array $packages): self
{
$this->updateAllowList = array_flip(array_map('strtolower', $packages));

return $this;
}










public function setUpdateAllowTransitiveDependencies(int $updateAllowTransitiveDependencies): self
{
if (!in_array($updateAllowTransitiveDependencies, [Request::UPDATE_ONLY_LISTED, Request::UPDATE_LISTED_WITH_TRANSITIVE_DEPS_NO_ROOT_REQUIRE, Request::UPDATE_LISTED_WITH_TRANSITIVE_DEPS], true)) {
throw new \RuntimeException("Invalid value for updateAllowTransitiveDependencies supplied");
}

$this->updateAllowTransitiveDependencies = $updateAllowTransitiveDependencies;

return $this;
}






public function setPreferStable(bool $preferStable = true): self
{
$this->preferStable = (bool) $preferStable;

return $this;
}






public function setPreferLowest(bool $preferLowest = true): self
{
$this->preferLowest = (bool) $preferLowest;

return $this;
}








public function setWriteLock(bool $writeLock = true): self
{
$this->writeLock = (bool) $writeLock;

return $this;
}








public function setExecuteOperations(bool $executeOperations = true): self
{
$this->executeOperations = (bool) $executeOperations;

return $this;
}






public function setAudit(bool $audit): self
{
$this->audit = $audit;

return $this;
}







public function setAuditFormat(string $auditFormat): self
{
$this->auditFormat = $auditFormat;

return $this;
}










public function disablePlugins(): self
{
$this->installationManager->disablePlugins();

return $this;
}




public function setSuggestedPackagesReporter(SuggestedPackagesReporter $suggestedPackagesReporter): self
{
$this->suggestedPackagesReporter = $suggestedPackagesReporter;

return $this;
}
}
