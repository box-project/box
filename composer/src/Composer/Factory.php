<?php declare(strict_types=1);











namespace Composer;

use Composer\Config\JsonConfigSource;
use Composer\Json\JsonFile;
use Composer\IO\IOInterface;
use Composer\Package\Archiver;
use Composer\Package\Version\VersionGuesser;
use Composer\Package\RootPackageInterface;
use Composer\Repository\RepositoryManager;
use Composer\Repository\RepositoryFactory;
use Composer\Util\Filesystem;
use Composer\Util\Platform;
use Composer\Util\ProcessExecutor;
use Composer\Util\HttpDownloader;
use Composer\Util\Loop;
use Composer\Util\Silencer;
use Composer\Plugin\PluginEvents;
use Composer\EventDispatcher\Event;
use Phar;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutput;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Autoload\AutoloadGenerator;
use Composer\Package\Version\VersionParser;
use Composer\Downloader\TransportException;
use Composer\Json\JsonValidationException;
use Composer\Repository\InstalledRepositoryInterface;
use UnexpectedValueException;
use ZipArchive;









class Factory
{



protected static function getHomeDir(): string
{
$home = Platform::getEnv('COMPOSER_HOME');
if ($home) {
return $home;
}

if (Platform::isWindows()) {
if (!Platform::getEnv('APPDATA')) {
throw new \RuntimeException('The APPDATA or COMPOSER_HOME environment variable must be set for composer to run correctly');
}

return rtrim(strtr(Platform::getEnv('APPDATA'), '\\', '/'), '/') . '/Composer';
}

$userDir = self::getUserDir();
$dirs = [];

if (self::useXdg()) {

$xdgConfig = Platform::getEnv('XDG_CONFIG_HOME');
if (!$xdgConfig) {
$xdgConfig = $userDir . '/.config';
}

$dirs[] = $xdgConfig . '/composer';
}

$dirs[] = $userDir . '/.composer';


foreach ($dirs as $dir) {
if (Silencer::call('is_dir', $dir)) {
return $dir;
}
}


return $dirs[0];
}

protected static function getCacheDir(string $home): string
{
$cacheDir = Platform::getEnv('COMPOSER_CACHE_DIR');
if ($cacheDir) {
return $cacheDir;
}

$homeEnv = Platform::getEnv('COMPOSER_HOME');
if ($homeEnv) {
return $homeEnv . '/cache';
}

if (Platform::isWindows()) {
if ($cacheDir = Platform::getEnv('LOCALAPPDATA')) {
$cacheDir .= '/Composer';
} else {
$cacheDir = $home . '/cache';
}

return rtrim(strtr($cacheDir, '\\', '/'), '/');
}

$userDir = self::getUserDir();
if (PHP_OS === 'Darwin') {

if (is_dir($home . '/cache') && !is_dir($userDir . '/Library/Caches/composer')) {
Silencer::call('rename', $home . '/cache', $userDir . '/Library/Caches/composer');
}

return $userDir . '/Library/Caches/composer';
}

if ($home === $userDir . '/.composer' && is_dir($home . '/cache')) {
return $home . '/cache';
}

if (self::useXdg()) {
$xdgCache = Platform::getEnv('XDG_CACHE_HOME') ?: $userDir . '/.cache';

return $xdgCache . '/composer';
}

return $home . '/cache';
}

protected static function getDataDir(string $home): string
{
$homeEnv = Platform::getEnv('COMPOSER_HOME');
if ($homeEnv) {
return $homeEnv;
}

if (Platform::isWindows()) {
return strtr($home, '\\', '/');
}

$userDir = self::getUserDir();
if ($home !== $userDir . '/.composer' && self::useXdg()) {
$xdgData = Platform::getEnv('XDG_DATA_HOME') ?: $userDir . '/.local/share';

return $xdgData . '/composer';
}

return $home;
}

public static function createConfig(?IOInterface $io = null, ?string $cwd = null): Config
{
$cwd = $cwd ?? Platform::getCwd(true);

$config = new Config(true, $cwd);


$home = self::getHomeDir();
$config->merge([
'config' => [
'home' => $home,
'cache-dir' => self::getCacheDir($home),
'data-dir' => self::getDataDir($home),
],
], Config::SOURCE_DEFAULT);


$file = new JsonFile($config->get('home').'/config.json');
if ($file->exists()) {
if ($io instanceof IOInterface) {
$io->writeError('Loading config file ' . $file->getPath(), true, IOInterface::DEBUG);
}
self::validateJsonSchema($io, $file);
$config->merge($file->read(), $file->getPath());
}
$config->setConfigSource(new JsonConfigSource($file));

$htaccessProtect = $config->get('htaccess-protect');
if ($htaccessProtect) {



$dirs = [$config->get('home'), $config->get('cache-dir'), $config->get('data-dir')];
foreach ($dirs as $dir) {
if (!file_exists($dir . '/.htaccess')) {
if (!is_dir($dir)) {
Silencer::call('mkdir', $dir, 0777, true);
}
Silencer::call('file_put_contents', $dir . '/.htaccess', 'Deny from all');
}
}
}


$file = new JsonFile($config->get('home').'/auth.json');
if ($file->exists()) {
if ($io instanceof IOInterface) {
$io->writeError('Loading config file ' . $file->getPath(), true, IOInterface::DEBUG);
}
self::validateJsonSchema($io, $file, JsonFile::AUTH_SCHEMA);
$config->merge(['config' => $file->read()], $file->getPath());
}
$config->setAuthConfigSource(new JsonConfigSource($file, true));


if ($composerAuthEnv = Platform::getEnv('COMPOSER_AUTH')) {
$authData = json_decode($composerAuthEnv);
if (null === $authData) {
throw new \UnexpectedValueException('COMPOSER_AUTH environment variable is malformed, should be a valid JSON object');
} else {
if ($io instanceof IOInterface) {
$io->writeError('Loading auth config from COMPOSER_AUTH', true, IOInterface::DEBUG);
}
self::validateJsonSchema($io, $authData, JsonFile::AUTH_SCHEMA, 'COMPOSER_AUTH');
$authData = json_decode($composerAuthEnv, true);
if (null !== $authData) {
$config->merge(['config' => $authData], 'COMPOSER_AUTH');
}
}
}

return $config;
}

public static function getComposerFile(): string
{
return trim((string) Platform::getEnv('COMPOSER')) ?: './composer.json';
}

public static function getLockFile(string $composerFile): string
{
return "json" === pathinfo($composerFile, PATHINFO_EXTENSION)
? substr($composerFile, 0, -4).'lock'
: $composerFile . '.lock';
}




public static function createAdditionalStyles(): array
{
return [
'highlight' => new OutputFormatterStyle('red'),
'warning' => new OutputFormatterStyle('black', 'yellow'),
];
}

public static function createOutput(): ConsoleOutput
{
$styles = self::createAdditionalStyles();
$formatter = new OutputFormatter(false, $styles);

return new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, null, $formatter);
}















public function createComposer(IOInterface $io, $localConfig = null, $disablePlugins = false, ?string $cwd = null, bool $fullLoad = true, bool $disableScripts = false)
{

if (is_string($localConfig) && is_file($localConfig) && null === $cwd) {
$cwd = dirname($localConfig);
}

$cwd = $cwd ?? Platform::getCwd(true);


if (null === $localConfig) {
$localConfig = static::getComposerFile();
}

$localConfigSource = Config::SOURCE_UNKNOWN;
if (is_string($localConfig)) {
$composerFile = $localConfig;

$file = new JsonFile($localConfig, null, $io);

if (!$file->exists()) {
if ($localConfig === './composer.json' || $localConfig === 'composer.json') {
$message = 'Composer could not find a composer.json file in '.$cwd;
} else {
$message = 'Composer could not find the config file: '.$localConfig;
}
$instructions = $fullLoad ? 'To initialize a project, please create a composer.json file. See https://getcomposer.org/basic-usage' : '';
throw new \InvalidArgumentException($message.PHP_EOL.$instructions);
}

if (!Platform::isInputCompletionProcess()) {
try {
$file->validateSchema(JsonFile::LAX_SCHEMA);
} catch (JsonValidationException $e) {
$errors = ' - ' . implode(PHP_EOL . ' - ', $e->getErrors());
$message = $e->getMessage() . ':' . PHP_EOL . $errors;
throw new JsonValidationException($message);
}
}

$localConfig = $file->read();
$localConfigSource = $file->getPath();
}


$config = static::createConfig($io, $cwd);
$config->merge($localConfig, $localConfigSource);
if (isset($composerFile)) {
$io->writeError('Loading config file ' . $composerFile .' ('.realpath($composerFile).')', true, IOInterface::DEBUG);
$config->setConfigSource(new JsonConfigSource(new JsonFile(realpath($composerFile), null, $io)));

$localAuthFile = new JsonFile(dirname(realpath($composerFile)) . '/auth.json', null, $io);
if ($localAuthFile->exists()) {
$io->writeError('Loading config file ' . $localAuthFile->getPath(), true, IOInterface::DEBUG);
self::validateJsonSchema($io, $localAuthFile, JsonFile::AUTH_SCHEMA);
$config->merge(['config' => $localAuthFile->read()], $localAuthFile->getPath());
$config->setLocalAuthConfigSource(new JsonConfigSource($localAuthFile, true));
}
}

$vendorDir = $config->get('vendor-dir');


$composer = $fullLoad ? new Composer() : new PartialComposer();
$composer->setConfig($config);

if ($fullLoad) {

$io->loadConfiguration($config);


if (!class_exists('Composer\InstalledVersions', false) && file_exists($installedVersionsPath = $config->get('vendor-dir').'/composer/InstalledVersions.php')) {
include $installedVersionsPath;
}
}

$httpDownloader = self::createHttpDownloader($io, $config);
$process = new ProcessExecutor($io);
$loop = new Loop($httpDownloader, $process);
$composer->setLoop($loop);


$dispatcher = new EventDispatcher($composer, $io, $process);
$dispatcher->setRunScripts(!$disableScripts);
$composer->setEventDispatcher($dispatcher);


$rm = RepositoryFactory::manager($io, $config, $httpDownloader, $dispatcher, $process);
$composer->setRepositoryManager($rm);



if (!$fullLoad && !isset($localConfig['version'])) {
$localConfig['version'] = '1.0.0';
}


$parser = new VersionParser;
$guesser = new VersionGuesser($config, $process, $parser);
$loader = $this->loadRootPackage($rm, $config, $parser, $guesser, $io);
$package = $loader->load($localConfig, 'Composer\Package\RootPackage', $cwd);
$composer->setPackage($package);


$this->addLocalRepository($io, $rm, $vendorDir, $package, $process);


$im = $this->createInstallationManager($loop, $io, $dispatcher);
$composer->setInstallationManager($im);

if ($composer instanceof Composer) {

$dm = $this->createDownloadManager($io, $config, $httpDownloader, $process, $dispatcher);
$composer->setDownloadManager($dm);


$generator = new AutoloadGenerator($dispatcher, $io);
$composer->setAutoloadGenerator($generator);


$am = $this->createArchiveManager($config, $dm, $loop);
$composer->setArchiveManager($am);
}


$this->createDefaultInstallers($im, $composer, $io, $process);


if ($composer instanceof Composer && isset($composerFile)) {
$lockFile = self::getLockFile($composerFile);
if (!$config->get('lock') && file_exists($lockFile)) {
$io->writeError('<warning>'.$lockFile.' is present but ignored as the "lock" config option is disabled.</warning>');
}

$locker = new Package\Locker($io, new JsonFile($config->get('lock') ? $lockFile : Platform::getDevNull(), null, $io), $im, file_get_contents($composerFile), $process);
$composer->setLocker($locker);
} elseif ($composer instanceof Composer) {
$locker = new Package\Locker($io, new JsonFile(Platform::getDevNull(), null, $io), $im, JsonFile::encode($localConfig), $process);
$composer->setLocker($locker);
}

if ($composer instanceof Composer) {
$globalComposer = null;
if (realpath($config->get('home')) !== $cwd) {
$globalComposer = $this->createGlobalComposer($io, $config, $disablePlugins, $disableScripts);
}

$pm = $this->createPluginManager($io, $composer, $globalComposer, $disablePlugins);
$composer->setPluginManager($pm);

if (realpath($config->get('home')) === $cwd) {
$pm->setRunningInGlobalDir(true);
}

$pm->loadInstalledPlugins();
}

if ($fullLoad) {
$initEvent = new Event(PluginEvents::INIT);
$composer->getEventDispatcher()->dispatch($initEvent->getName(), $initEvent);



$this->purgePackages($rm->getLocalRepository(), $im);
}

return $composer;
}





public static function createGlobal(IOInterface $io, bool $disablePlugins = false, bool $disableScripts = false): ?Composer
{
$factory = new static();

return $factory->createGlobalComposer($io, static::createConfig($io), $disablePlugins, $disableScripts, true);
}




protected function addLocalRepository(IOInterface $io, RepositoryManager $rm, string $vendorDir, RootPackageInterface $rootPackage, ?ProcessExecutor $process = null): void
{
$fs = null;
if ($process) {
$fs = new Filesystem($process);
}

$rm->setLocalRepository(new Repository\InstalledFilesystemRepository(new JsonFile($vendorDir.'/composer/installed.json', null, $io), true, $rootPackage, $fs));
}






protected function createGlobalComposer(IOInterface $io, Config $config, $disablePlugins, bool $disableScripts, bool $fullLoad = false): ?PartialComposer
{

$disablePlugins = $disablePlugins === 'global' || $disablePlugins === true;

$composer = null;
try {
$composer = $this->createComposer($io, $config->get('home') . '/composer.json', $disablePlugins, $config->get('home'), $fullLoad, $disableScripts);
} catch (\Exception $e) {
$io->writeError('Failed to initialize global composer: '.$e->getMessage(), true, IOInterface::DEBUG);
}

return $composer;
}





public function createDownloadManager(IOInterface $io, Config $config, HttpDownloader $httpDownloader, ProcessExecutor $process, ?EventDispatcher $eventDispatcher = null): Downloader\DownloadManager
{
$cache = null;
if ($config->get('cache-files-ttl') > 0) {
$cache = new Cache($io, $config->get('cache-files-dir'), 'a-z0-9_./');
$cache->setReadOnly($config->get('cache-read-only'));
}

$fs = new Filesystem($process);

$dm = new Downloader\DownloadManager($io, false, $fs);
switch ($preferred = $config->get('preferred-install')) {
case 'dist':
$dm->setPreferDist(true);
break;
case 'source':
$dm->setPreferSource(true);
break;
case 'auto':
default:

break;
}

if (is_array($preferred)) {
$dm->setPreferences($preferred);
}

$dm->setDownloader('git', new Downloader\GitDownloader($io, $config, $process, $fs));
$dm->setDownloader('svn', new Downloader\SvnDownloader($io, $config, $process, $fs));
$dm->setDownloader('fossil', new Downloader\FossilDownloader($io, $config, $process, $fs));
$dm->setDownloader('hg', new Downloader\HgDownloader($io, $config, $process, $fs));
$dm->setDownloader('perforce', new Downloader\PerforceDownloader($io, $config, $process, $fs));
$dm->setDownloader('zip', new Downloader\ZipDownloader($io, $config, $httpDownloader, $eventDispatcher, $cache, $fs, $process));
$dm->setDownloader('rar', new Downloader\RarDownloader($io, $config, $httpDownloader, $eventDispatcher, $cache, $fs, $process));
$dm->setDownloader('tar', new Downloader\TarDownloader($io, $config, $httpDownloader, $eventDispatcher, $cache, $fs, $process));
$dm->setDownloader('gzip', new Downloader\GzipDownloader($io, $config, $httpDownloader, $eventDispatcher, $cache, $fs, $process));
$dm->setDownloader('xz', new Downloader\XzDownloader($io, $config, $httpDownloader, $eventDispatcher, $cache, $fs, $process));
$dm->setDownloader('phar', new Downloader\PharDownloader($io, $config, $httpDownloader, $eventDispatcher, $cache, $fs, $process));
$dm->setDownloader('file', new Downloader\FileDownloader($io, $config, $httpDownloader, $eventDispatcher, $cache, $fs, $process));
$dm->setDownloader('path', new Downloader\PathDownloader($io, $config, $httpDownloader, $eventDispatcher, $cache, $fs, $process));

return $dm;
}






public function createArchiveManager(Config $config, Downloader\DownloadManager $dm, Loop $loop)
{
$am = new Archiver\ArchiveManager($dm, $loop);
if (class_exists(ZipArchive::class)) {
$am->addArchiver(new Archiver\ZipArchiver);
}
if (class_exists(Phar::class)) {
$am->addArchiver(new Archiver\PharArchiver);
}

return $am;
}




protected function createPluginManager(IOInterface $io, Composer $composer, ?PartialComposer $globalComposer = null, $disablePlugins = false): Plugin\PluginManager
{
return new Plugin\PluginManager($io, $composer, $globalComposer, $disablePlugins);
}

public function createInstallationManager(Loop $loop, IOInterface $io, ?EventDispatcher $eventDispatcher = null): Installer\InstallationManager
{
return new Installer\InstallationManager($loop, $io, $eventDispatcher);
}

protected function createDefaultInstallers(Installer\InstallationManager $im, PartialComposer $composer, IOInterface $io, ?ProcessExecutor $process = null): void
{
$fs = new Filesystem($process);
$binaryInstaller = new Installer\BinaryInstaller($io, rtrim($composer->getConfig()->get('bin-dir'), '/'), $composer->getConfig()->get('bin-compat'), $fs, rtrim($composer->getConfig()->get('vendor-dir'), '/'));

$im->addInstaller(new Installer\LibraryInstaller($io, $composer, null, $fs, $binaryInstaller));
$im->addInstaller(new Installer\PluginInstaller($io, $composer, $fs, $binaryInstaller));
$im->addInstaller(new Installer\MetapackageInstaller($io));
}





protected function purgePackages(InstalledRepositoryInterface $repo, Installer\InstallationManager $im): void
{
foreach ($repo->getPackages() as $package) {
if (!$im->isPackageInstalled($repo, $package)) {
$repo->removePackage($package);
}
}
}

protected function loadRootPackage(RepositoryManager $rm, Config $config, VersionParser $parser, VersionGuesser $guesser, IOInterface $io): Package\Loader\RootPackageLoader
{
return new Package\Loader\RootPackageLoader($rm, $config, $parser, $guesser, $io);
}








public static function create(IOInterface $io, $config = null, $disablePlugins = false, bool $disableScripts = false): Composer
{
$factory = new static();





if ($config !== null && $config !== self::getComposerFile() && $disablePlugins === false) {
$disablePlugins = 'local';
}

return $factory->createComposer($io, $config, $disablePlugins, null, true, $disableScripts);
}








public static function createHttpDownloader(IOInterface $io, Config $config, array $options = []): HttpDownloader
{
static $warned = false;
$disableTls = false;

if (isset($_SERVER['argv']) && in_array('disable-tls', $_SERVER['argv']) && (in_array('conf', $_SERVER['argv']) || in_array('config', $_SERVER['argv']))) {
$warned = true;
$disableTls = !extension_loaded('openssl');
} elseif ($config->get('disable-tls') === true) {
if (!$warned) {
$io->writeError('<warning>You are running Composer with SSL/TLS protection disabled.</warning>');
}
$warned = true;
$disableTls = true;
} elseif (!extension_loaded('openssl')) {
throw new Exception\NoSslException('The openssl extension is required for SSL/TLS protection but is not available. '
. 'If you can not enable the openssl extension, you can disable this error, at your own risk, by setting the \'disable-tls\' option to true.');
}
$httpDownloaderOptions = [];
if ($disableTls === false) {
if ('' !== $config->get('cafile')) {
$httpDownloaderOptions['ssl']['cafile'] = $config->get('cafile');
}
if ('' !== $config->get('capath')) {
$httpDownloaderOptions['ssl']['capath'] = $config->get('capath');
}
$httpDownloaderOptions = array_replace_recursive($httpDownloaderOptions, $options);
}
try {
$httpDownloader = new HttpDownloader($io, $config, $httpDownloaderOptions, $disableTls);
} catch (TransportException $e) {
if (false !== strpos($e->getMessage(), 'cafile')) {
$io->write('<error>Unable to locate a valid CA certificate file. You must set a valid \'cafile\' option.</error>');
$io->write('<error>A valid CA certificate file is required for SSL/TLS protection.</error>');
$io->write('<error>You can disable this error, at your own risk, by setting the \'disable-tls\' option to true.</error>');
}
throw $e;
}

return $httpDownloader;
}

private static function useXdg(): bool
{
foreach (array_keys($_SERVER) as $key) {
if (strpos($key, 'XDG_') === 0) {
return true;
}
}

if (Silencer::call('is_dir', '/etc/xdg')) {
return true;
}

return false;
}




private static function getUserDir(): string
{
$home = Platform::getEnv('HOME');
if (!$home) {
throw new \RuntimeException('The HOME or COMPOSER_HOME environment variable must be set for composer to run correctly');
}

return rtrim(strtr($home, '\\', '/'), '/');
}





private static function validateJsonSchema(?IOInterface $io, $fileOrData, int $schema = JsonFile::LAX_SCHEMA, ?string $source = null): void
{
if (Platform::isInputCompletionProcess()) {
return;
}

try {
if ($fileOrData instanceof JsonFile) {
$fileOrData->validateSchema($schema);
} else {
if (null === $source) {
throw new \InvalidArgumentException('$source is required to be provided if $fileOrData is arbitrary data');
}
JsonFile::validateJsonSchema($source, $fileOrData, $schema);
}
} catch (JsonValidationException $e) {
$msg = $e->getMessage().', this may result in errors and should be resolved:'.PHP_EOL.' - '.implode(PHP_EOL.' - ', $e->getErrors());
if ($io instanceof IOInterface) {
$io->writeError('<warning>'.$msg.'</>');
} else {
throw new UnexpectedValueException($msg);
}
}
}
}
