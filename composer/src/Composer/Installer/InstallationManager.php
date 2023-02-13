<?php declare(strict_types=1);











namespace Composer\Installer;

use Composer\IO\IOInterface;
use Composer\IO\ConsoleIO;
use Composer\Package\PackageInterface;
use Composer\Package\AliasPackage;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\MarkAliasInstalledOperation;
use Composer\DependencyResolver\Operation\MarkAliasUninstalledOperation;
use Composer\Downloader\FileDownloader;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Util\Loop;
use Composer\Util\Platform;
use React\Promise\PromiseInterface;
use Seld\Signal\SignalHandler;








class InstallationManager
{

private $installers = [];

private $cache = [];

private $notifiablePackages = [];

private $loop;

private $io;

private $eventDispatcher;

private $outputProgress;

public function __construct(Loop $loop, IOInterface $io, ?EventDispatcher $eventDispatcher = null)
{
$this->loop = $loop;
$this->io = $io;
$this->eventDispatcher = $eventDispatcher;
}

public function reset(): void
{
$this->notifiablePackages = [];
FileDownloader::$downloadMetadata = [];
}






public function addInstaller(InstallerInterface $installer): void
{
array_unshift($this->installers, $installer);
$this->cache = [];
}






public function removeInstaller(InstallerInterface $installer): void
{
if (false !== ($key = array_search($installer, $this->installers, true))) {
array_splice($this->installers, $key, 1);
$this->cache = [];
}
}








public function disablePlugins(): void
{
foreach ($this->installers as $i => $installer) {
if (!$installer instanceof PluginInstaller) {
continue;
}

unset($this->installers[$i]);
}
}








public function getInstaller(string $type): InstallerInterface
{
$type = strtolower($type);

if (isset($this->cache[$type])) {
return $this->cache[$type];
}

foreach ($this->installers as $installer) {
if ($installer->supports($type)) {
return $this->cache[$type] = $installer;
}
}

throw new \InvalidArgumentException('Unknown installer type: '.$type);
}







public function isPackageInstalled(InstalledRepositoryInterface $repo, PackageInterface $package): bool
{
if ($package instanceof AliasPackage) {
return $repo->hasPackage($package) && $this->isPackageInstalled($repo, $package->getAliasOf());
}

return $this->getInstaller($package->getType())->isInstalled($repo, $package);
}







public function ensureBinariesPresence(PackageInterface $package): void
{
try {
$installer = $this->getInstaller($package->getType());
} catch (\InvalidArgumentException $e) {

return;
}


if ($installer instanceof BinaryPresenceInterface) {
$installer->ensureBinariesPresence($package);
}
}










public function execute(InstalledRepositoryInterface $repo, array $operations, bool $devMode = true, bool $runScripts = true, bool $downloadOnly = false): void
{

$cleanupPromises = [];

$signalHandler = SignalHandler::create([SignalHandler::SIGINT, SignalHandler::SIGTERM, SignalHandler::SIGHUP], function (string $signal, SignalHandler $handler) use (&$cleanupPromises) {
$this->io->writeError('Received '.$signal.', aborting', true, IOInterface::DEBUG);
$this->runCleanup($cleanupPromises);
$handler->exitWithLastSignal();
});

try {


$batches = [];
$batch = [];
foreach ($operations as $index => $operation) {
if ($operation instanceof UpdateOperation || $operation instanceof InstallOperation) {
$package = $operation instanceof UpdateOperation ? $operation->getTargetPackage() : $operation->getPackage();
if ($package->getType() === 'composer-plugin' && ($extra = $package->getExtra()) && isset($extra['plugin-modifies-downloads']) && $extra['plugin-modifies-downloads'] === true) {
if ($batch) {
$batches[] = $batch;
}
$batches[] = [$index => $operation];
$batch = [];

continue;
}
}
$batch[$index] = $operation;
}

if ($batch) {
$batches[] = $batch;
}

foreach ($batches as $batch) {
$this->downloadAndExecuteBatch($repo, $batch, $cleanupPromises, $devMode, $runScripts, $downloadOnly, $operations);
}
} catch (\Exception $e) {
$this->runCleanup($cleanupPromises);

throw $e;
} finally {
$signalHandler->unregister();
}

if ($downloadOnly) {
return;
}




$repo->write($devMode, $this);
}






private function downloadAndExecuteBatch(InstalledRepositoryInterface $repo, array $operations, array &$cleanupPromises, bool $devMode, bool $runScripts, bool $downloadOnly, array $allOperations): void
{
$promises = [];

foreach ($operations as $index => $operation) {
$opType = $operation->getOperationType();


if (!in_array($opType, ['update', 'install', 'uninstall'])) {
continue;
}

if ($opType === 'update') {

$package = $operation->getTargetPackage();
$initialPackage = $operation->getInitialPackage();
} else {

$package = $operation->getPackage();
$initialPackage = null;
}
$installer = $this->getInstaller($package->getType());

$cleanupPromises[$index] = static function () use ($opType, $installer, $package, $initialPackage): ?PromiseInterface {


if (!$package->getInstallationSource()) {
return \React\Promise\resolve(null);
}

return $installer->cleanup($opType, $package, $initialPackage);
};

if ($opType !== 'uninstall') {
$promise = $installer->download($package, $initialPackage);
if ($promise) {
$promises[] = $promise;
}
}
}


if (count($promises)) {
$this->waitOnPromises($promises);
}

if ($downloadOnly) {
$this->runCleanup($cleanupPromises);
return;
}



$batches = [];
$batch = [];
foreach ($operations as $index => $operation) {
if ($operation instanceof InstallOperation || $operation instanceof UpdateOperation) {
$package = $operation instanceof UpdateOperation ? $operation->getTargetPackage() : $operation->getPackage();
if ($package->getType() === 'composer-plugin' || $package->getType() === 'composer-installer') {
if ($batch) {
$batches[] = $batch;
}
$batches[] = [$index => $operation];
$batch = [];

continue;
}
}
$batch[$index] = $operation;
}

if ($batch) {
$batches[] = $batch;
}

foreach ($batches as $batch) {
$this->executeBatch($repo, $batch, $cleanupPromises, $devMode, $runScripts, $allOperations);
}
}






private function executeBatch(InstalledRepositoryInterface $repo, array $operations, array $cleanupPromises, bool $devMode, bool $runScripts, array $allOperations): void
{
$promises = [];
$postExecCallbacks = [];

foreach ($operations as $index => $operation) {
$opType = $operation->getOperationType();


if (!in_array($opType, ['update', 'install', 'uninstall'])) {

if ($this->io->isDebug()) {
$this->io->writeError('  - ' . $operation->show(false));
}
$this->{$opType}($repo, $operation);

continue;
}

if ($opType === 'update') {

$package = $operation->getTargetPackage();
$initialPackage = $operation->getInitialPackage();
} else {

$package = $operation->getPackage();
$initialPackage = null;
}

$installer = $this->getInstaller($package->getType());

$eventName = [
'install' => PackageEvents::PRE_PACKAGE_INSTALL,
'update' => PackageEvents::PRE_PACKAGE_UPDATE,
'uninstall' => PackageEvents::PRE_PACKAGE_UNINSTALL,
][$opType] ?? null;

if (null !== $eventName && $runScripts && $this->eventDispatcher) {
$this->eventDispatcher->dispatchPackageEvent($eventName, $devMode, $repo, $allOperations, $operation);
}

$dispatcher = $this->eventDispatcher;
$io = $this->io;

$promise = $installer->prepare($opType, $package, $initialPackage);
if (!$promise instanceof PromiseInterface) {
$promise = \React\Promise\resolve(null);
}

$promise = $promise->then(function () use ($opType, $repo, $operation) {
return $this->{$opType}($repo, $operation);
})->then($cleanupPromises[$index])
->then(function () use ($devMode, $repo): void {
$repo->write($devMode, $this);
}, static function ($e) use ($opType, $package, $io): void {
$io->writeError('    <error>' . ucfirst($opType) .' of '.$package->getPrettyName().' failed</error>');

throw $e;
});

$eventName = [
'install' => PackageEvents::POST_PACKAGE_INSTALL,
'update' => PackageEvents::POST_PACKAGE_UPDATE,
'uninstall' => PackageEvents::POST_PACKAGE_UNINSTALL,
][$opType] ?? null;

if (null !== $eventName && $runScripts && $dispatcher) {
$postExecCallbacks[] = static function () use ($dispatcher, $eventName, $devMode, $repo, $allOperations, $operation): void {
$dispatcher->dispatchPackageEvent($eventName, $devMode, $repo, $allOperations, $operation);
};
}

$promises[] = $promise;
}


if (count($promises)) {
$this->waitOnPromises($promises);
}

Platform::workaroundFilesystemIssues();

foreach ($postExecCallbacks as $cb) {
$cb();
}
}




private function waitOnPromises(array $promises): void
{
$progress = null;
if (
$this->outputProgress
&& $this->io instanceof ConsoleIO
&& !Platform::getEnv('CI')
&& !$this->io->isDebug()
&& count($promises) > 1
) {
$progress = $this->io->getProgressBar();
}
$this->loop->wait($promises, $progress);
if ($progress) {
$progress->clear();

if (!$this->io->isDecorated()) {
$this->io->writeError('');
}
}
}






public function download(PackageInterface $package): ?PromiseInterface
{
$installer = $this->getInstaller($package->getType());
$promise = $installer->cleanup("install", $package);

return $promise;
}







public function install(InstalledRepositoryInterface $repo, InstallOperation $operation): ?PromiseInterface
{
$package = $operation->getPackage();
$installer = $this->getInstaller($package->getType());
$promise = $installer->install($repo, $package);
$this->markForNotification($package);

return $promise;
}







public function update(InstalledRepositoryInterface $repo, UpdateOperation $operation): ?PromiseInterface
{
$initial = $operation->getInitialPackage();
$target = $operation->getTargetPackage();

$initialType = $initial->getType();
$targetType = $target->getType();

if ($initialType === $targetType) {
$installer = $this->getInstaller($initialType);
$promise = $installer->update($repo, $initial, $target);
$this->markForNotification($target);
} else {
$promise = $this->getInstaller($initialType)->uninstall($repo, $initial);
if (!$promise instanceof PromiseInterface) {
$promise = \React\Promise\resolve(null);
}

$installer = $this->getInstaller($targetType);
$promise = $promise->then(static function () use ($installer, $repo, $target): PromiseInterface {
$promise = $installer->install($repo, $target);
if ($promise instanceof PromiseInterface) {
return $promise;
}

return \React\Promise\resolve(null);
});
}

return $promise;
}







public function uninstall(InstalledRepositoryInterface $repo, UninstallOperation $operation): ?PromiseInterface
{
$package = $operation->getPackage();
$installer = $this->getInstaller($package->getType());

return $installer->uninstall($repo, $package);
}







public function markAliasInstalled(InstalledRepositoryInterface $repo, MarkAliasInstalledOperation $operation): void
{
$package = $operation->getPackage();

if (!$repo->hasPackage($package)) {
$repo->addPackage(clone $package);
}
}







public function markAliasUninstalled(InstalledRepositoryInterface $repo, MarkAliasUninstalledOperation $operation): void
{
$package = $operation->getPackage();

$repo->removePackage($package);
}






public function getInstallPath(PackageInterface $package): string
{
$installer = $this->getInstaller($package->getType());

return $installer->getInstallPath($package);
}

public function setOutputProgress(bool $outputProgress): void
{
$this->outputProgress = $outputProgress;
}

public function notifyInstalls(IOInterface $io): void
{
$promises = [];

try {
foreach ($this->notifiablePackages as $repoUrl => $packages) {

if (strpos($repoUrl, '%package%')) {
foreach ($packages as $package) {
$url = str_replace('%package%', $package->getPrettyName(), $repoUrl);

$params = [
'version' => $package->getPrettyVersion(),
'version_normalized' => $package->getVersion(),
];
$opts = [
'retry-auth-failure' => false,
'http' => [
'method' => 'POST',
'header' => ['Content-type: application/x-www-form-urlencoded'],
'content' => http_build_query($params, '', '&'),
'timeout' => 3,
],
];

$promises[] = $this->loop->getHttpDownloader()->add($url, $opts);
}

continue;
}

$postData = ['downloads' => []];
foreach ($packages as $package) {
$packageNotification = [
'name' => $package->getPrettyName(),
'version' => $package->getVersion(),
];
if (strpos($repoUrl, 'packagist.org/') !== false) {
if (isset(FileDownloader::$downloadMetadata[$package->getName()])) {
$packageNotification['downloaded'] = FileDownloader::$downloadMetadata[$package->getName()];
} else {
$packageNotification['downloaded'] = false;
}
}
$postData['downloads'][] = $packageNotification;
}

$opts = [
'retry-auth-failure' => false,
'http' => [
'method' => 'POST',
'header' => ['Content-Type: application/json'],
'content' => json_encode($postData),
'timeout' => 6,
],
];

$promises[] = $this->loop->getHttpDownloader()->add($repoUrl, $opts);
}

$this->loop->wait($promises);
} catch (\Exception $e) {
}

$this->reset();
}

private function markForNotification(PackageInterface $package): void
{
if ($package->getNotificationUrl()) {
$this->notifiablePackages[$package->getNotificationUrl()][$package->getName()] = $package;
}
}





private function runCleanup(array $cleanupPromises): void
{
$promises = [];

$this->loop->abortJobs();

foreach ($cleanupPromises as $cleanup) {
$promises[] = new \React\Promise\Promise(static function ($resolve, $reject) use ($cleanup): void {
$promise = $cleanup();
if (!$promise instanceof PromiseInterface) {
$resolve();
} else {
$promise->then(static function () use ($resolve): void {
$resolve();
});
}
});
}

if (!empty($promises)) {
$this->loop->wait($promises);
}
}
}
