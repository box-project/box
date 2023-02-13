<?php declare(strict_types=1);


namespace Composer\Plugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\InstallerInterface;
use Composer\IO\IOInterface;
use Composer\Package\BasePackage;
use Composer\Package\CompletePackage;
use Composer\Package\Locker;
use Composer\Package\Package;
use Composer\Package\Version\VersionParser;
use Composer\PartialComposer;
use Composer\Pcre\Preg;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\InstalledRepository;
use Composer\Repository\RootPackageRepository;
use Composer\Package\PackageInterface;
use Composer\Package\Link;
use Composer\Semver\Constraint\Constraint;
use Composer\Plugin\Capability\Capability;
use Composer\Util\PackageSorter;


class PluginManager
{

    protected $composer;

    protected $io;

    protected $globalComposer;

    protected $versionParser;

    protected $disablePlugins = false;


    protected $plugins = [];

    protected $registeredPlugins = [];


    private $allowPluginRules;


    private $allowGlobalPluginRules;


    private $runningInGlobalDir = false;


    private static $classCounter = 0;


    public function __construct(IOInterface $io, Composer $composer, ?PartialComposer $globalComposer = null, $disablePlugins = false)
    {
        $this->io = $io;
        $this->composer = $composer;
        $this->globalComposer = $globalComposer;
        $this->versionParser = new VersionParser();
        $this->disablePlugins = $disablePlugins;
        $this->allowPluginRules = $this->parseAllowedPlugins($composer->getConfig()->get('allow-plugins'), $composer->getLocker());
        $this->allowGlobalPluginRules = $this->parseAllowedPlugins($globalComposer !== null ? $globalComposer->getConfig()->get('allow-plugins') : false);
    }

    public function setRunningInGlobalDir(bool $runningInGlobalDir): void
    {
        $this->runningInGlobalDir = $runningInGlobalDir;
    }


    public function loadInstalledPlugins(): void
    {
        if (!$this->arePluginsDisabled('local')) {
            $repo = $this->composer->getRepositoryManager()->getLocalRepository();
            $this->loadRepository($repo, false);
        }

        if ($this->globalComposer !== null && !$this->arePluginsDisabled('global')) {
            $this->loadRepository($this->globalComposer->getRepositoryManager()->getLocalRepository(), true);
        }
    }


    public function deactivateInstalledPlugins(): void
    {
        if (!$this->arePluginsDisabled('local')) {
            $repo = $this->composer->getRepositoryManager()->getLocalRepository();
            $this->deactivateRepository($repo, false);
        }

        if ($this->globalComposer !== null && !$this->arePluginsDisabled('global')) {
            $this->deactivateRepository($this->globalComposer->getRepositoryManager()->getLocalRepository(), true);
        }
    }


    public function getPlugins(): array
    {
        return $this->plugins;
    }


    public function getGlobalComposer(): ?PartialComposer
    {
        return $this->globalComposer;
    }


    public function registerPackage(PackageInterface $package, bool $failOnMissingClasses = false, bool $isGlobalPlugin = false): void
    {
        if ($this->arePluginsDisabled($isGlobalPlugin ? 'global' : 'local')) {
            return;
        }

        if ($package->getType() === 'composer-plugin') {
            $requiresComposer = null;
            foreach ($package->getRequires() as $link) {
                if ('composer-plugin-api' === $link->getTarget()) {
                    $requiresComposer = $link->getConstraint();
                    break;
                }
            }

            if (!$requiresComposer) {
                throw new \RuntimeException("Plugin " . $package->getName() . " is missing a require statement for a version of the composer-plugin-api package.");
            }

            $currentPluginApiVersion = $this->getPluginApiVersion();
            $currentPluginApiConstraint = new Constraint('==', $this->versionParser->normalize($currentPluginApiVersion));

            if ($requiresComposer->getPrettyString() === $this->getPluginApiVersion()) {
                $this->io->writeError('<warning>The "' . $package->getName() . '" plugin requires composer-plugin-api ' . $this->getPluginApiVersion() . ', this *WILL* break in the future and it should be fixed ASAP (require ^' . $this->getPluginApiVersion() . ' instead for example).</warning>');
            } elseif (!$requiresComposer->matches($currentPluginApiConstraint)) {
                $this->io->writeError('<warning>The "' . $package->getName() . '" plugin ' . ($isGlobalPlugin || $this->runningInGlobalDir ? '(installed globally) ' : '') . 'was skipped because it requires a Plugin API version ("' . $requiresComposer->getPrettyString() . '") that does not match your Composer installation ("' . $currentPluginApiVersion . '"). You may need to run composer update with the "--no-plugins" option.</warning>');

                return;
            }

            if ($package->getName() === 'symfony/flex' && Preg::isMatch('{^[0-9.]+$}', $package->getVersion()) && version_compare($package->getVersion(), '1.9.8', '<')) {
                $this->io->writeError('<warning>The "' . $package->getName() . '" plugin ' . ($isGlobalPlugin || $this->runningInGlobalDir ? '(installed globally) ' : '') . 'was skipped because it is not compatible with Composer 2+. Make sure to update it to version 1.9.8 or greater.</warning>');

                return;
            }
        }

        if (!$this->isPluginAllowed($package->getName(), $isGlobalPlugin, $package->getExtra()['plugin-optional'] ?? false)) {
            $this->io->writeError('Skipped loading "' . $package->getName() . '" ' . ($isGlobalPlugin || $this->runningInGlobalDir ? '(installed globally) ' : '') . 'as it is not in config.allow-plugins', true, IOInterface::DEBUG);

            return;
        }

        $oldInstallerPlugin = ($package->getType() === 'composer-installer');

        if (isset($this->registeredPlugins[$package->getName()])) {
            return;
        }

        $extra = $package->getExtra();
        if (empty($extra['class'])) {
            throw new \UnexpectedValueException('Error while installing ' . $package->getPrettyName() . ', composer-plugin packages should have a class defined in their extra key to be usable.');
        }
        $classes = is_array($extra['class']) ? $extra['class'] : [$extra['class']];

        $localRepo = $this->composer->getRepositoryManager()->getLocalRepository();
        $globalRepo = $this->globalComposer !== null ? $this->globalComposer->getRepositoryManager()->getLocalRepository() : null;

        $rootPackage = clone $this->composer->getPackage();


        $rootPackageAutoloads = $rootPackage->getAutoload();
        $rootPackageAutoloads['files'] = [];
        $rootPackage->setAutoload($rootPackageAutoloads);
        $rootPackageAutoloads = $rootPackage->getDevAutoload();
        $rootPackageAutoloads['files'] = [];
        $rootPackage->setDevAutoload($rootPackageAutoloads);
        unset($rootPackageAutoloads);

        $rootPackageRepo = new RootPackageRepository($rootPackage);
        $installedRepo = new InstalledRepository([$localRepo, $rootPackageRepo]);
        if ($globalRepo) {
            $installedRepo->addRepository($globalRepo);
        }

        $autoloadPackages = [$package->getName() => $package];
        $autoloadPackages = $this->collectDependencies($installedRepo, $autoloadPackages, $package);

        $generator = $this->composer->getAutoloadGenerator();
        $autoloads = [[$rootPackage, '']];
        foreach ($autoloadPackages as $autoloadPackage) {
            if ($autoloadPackage === $rootPackage) {
                continue;
            }

            $downloadPath = $this->getInstallPath($autoloadPackage, $globalRepo && $globalRepo->hasPackage($autoloadPackage));
            $autoloads[] = [$autoloadPackage, $downloadPath];
        }

        $map = $generator->parseAutoloads($autoloads, $rootPackage);
        $classLoader = $generator->createLoader($map, $this->composer->getConfig()->get('vendor-dir'));
        $classLoader->register(false);

        foreach ($map['files'] as $fileIdentifier => $file) {


            if ($fileIdentifier === '7e9bd612cc444b3eed788ebbe46263a0') {
                continue;
            }
            \Composer\Autoload\composerRequire($fileIdentifier, $file);
        }

        foreach ($classes as $class) {
            if (class_exists($class, false)) {
                $class = trim($class, '\\');
                $path = $classLoader->findFile($class);
                $code = file_get_contents($path);
                $separatorPos = strrpos($class, '\\');
                $className = $class;
                if ($separatorPos) {
                    $className = substr($class, $separatorPos + 1);
                }
                $code = Preg::replace('{^((?:final\s+)?(?:\s*))class\s+(' . preg_quote($className) . ')}mi', '$1class $2_composer_tmp' . self::$classCounter, $code, 1);
                $code = strtr($code, [
                    '__FILE__' => var_export($path, true),
                    '__DIR__' => var_export(dirname($path), true),
                    '__CLASS__' => var_export($class, true),
                ]);
                $code = Preg::replace('/^\s*<\?(php)?/i', '', $code, 1);
                eval($code);
                $class .= '_composer_tmp' . self::$classCounter;
                self::$classCounter++;
            }

            if ($oldInstallerPlugin) {
                if (!is_a($class, 'Composer\Installer\InstallerInterface', true)) {
                    throw new \RuntimeException('Could not activate plugin "' . $package->getName() . '" as "' . $class . '" does not implement Composer\Installer\InstallerInterface');
                }
                $this->io->writeError('<warning>Loading "' . $package->getName() . '" ' . ($isGlobalPlugin || $this->runningInGlobalDir ? '(installed globally) ' : '') . 'which is a legacy composer-installer built for Composer 1.x, it is likely to cause issues as you are running Composer 2.x.</warning>');
                $installer = new $class($this->io, $this->composer);
                $this->composer->getInstallationManager()->addInstaller($installer);
                $this->registeredPlugins[$package->getName()] = $installer;
            } elseif (class_exists($class)) {
                if (!is_a($class, 'Composer\Plugin\PluginInterface', true)) {
                    throw new \RuntimeException('Could not activate plugin "' . $package->getName() . '" as "' . $class . '" does not implement Composer\Plugin\PluginInterface');
                }
                $plugin = new $class();
                $this->addPlugin($plugin, $isGlobalPlugin, $package);
                $this->registeredPlugins[$package->getName()] = $plugin;
            } elseif ($failOnMissingClasses) {
                throw new \UnexpectedValueException('Plugin ' . $package->getName() . ' could not be initialized, class not found: ' . $class);
            }
        }
    }


    public function deactivatePackage(PackageInterface $package): void
    {
        if (!isset($this->registeredPlugins[$package->getName()])) {
            return;
        }

        $plugin = $this->registeredPlugins[$package->getName()];
        unset($this->registeredPlugins[$package->getName()]);
        if ($plugin instanceof InstallerInterface) {
            $this->composer->getInstallationManager()->removeInstaller($plugin);
        } else {
            $this->removePlugin($plugin);
        }
    }


    public function uninstallPackage(PackageInterface $package): void
    {
        if (!isset($this->registeredPlugins[$package->getName()])) {
            return;
        }

        $plugin = $this->registeredPlugins[$package->getName()];
        if ($plugin instanceof InstallerInterface) {
            $this->deactivatePackage($package);
        } else {
            unset($this->registeredPlugins[$package->getName()]);
            $this->removePlugin($plugin);
            $this->uninstallPlugin($plugin);
        }
    }


    protected function getPluginApiVersion(): string
    {
        return PluginInterface::PLUGIN_API_VERSION;
    }


    public function addPlugin(PluginInterface $plugin, bool $isGlobalPlugin = false, ?PackageInterface $sourcePackage = null): void
    {
        if ($this->arePluginsDisabled($isGlobalPlugin ? 'global' : 'local')) {
            return;
        }

        if ($sourcePackage === null) {
            trigger_error('Calling PluginManager::addPlugin without $sourcePackage is deprecated, if you are using this please get in touch with us to explain the use case', E_USER_DEPRECATED);
        } elseif (!$this->isPluginAllowed($sourcePackage->getName(), $isGlobalPlugin, $sourcePackage->getExtra()['plugin-optional'] ?? false)) {
            $this->io->writeError('Skipped loading "' . get_class($plugin) . ' from ' . $sourcePackage->getName() . '" ' . ($isGlobalPlugin || $this->runningInGlobalDir ? '(installed globally) ' : '') . ' as it is not in config.allow-plugins', true, IOInterface::DEBUG);

            return;
        }

        $details = [];
        if ($sourcePackage) {
            $details[] = 'from ' . $sourcePackage->getName();
        }
        if ($isGlobalPlugin || $this->runningInGlobalDir) {
            $details[] = 'installed globally';
        }
        $this->io->writeError('Loading plugin ' . get_class($plugin) . ($details ? ' (' . implode(', ', $details) . ')' : ''), true, IOInterface::DEBUG);
        $this->plugins[] = $plugin;
        $plugin->activate($this->composer, $this->io);

        if ($plugin instanceof EventSubscriberInterface) {
            $this->composer->getEventDispatcher()->addSubscriber($plugin);
        }
    }


    public function removePlugin(PluginInterface $plugin): void
    {
        $index = array_search($plugin, $this->plugins, true);
        if ($index === false) {
            return;
        }

        $this->io->writeError('Unloading plugin ' . get_class($plugin), true, IOInterface::DEBUG);
        unset($this->plugins[$index]);
        $plugin->deactivate($this->composer, $this->io);

        $this->composer->getEventDispatcher()->removeListener($plugin);
    }


    public function uninstallPlugin(PluginInterface $plugin): void
    {
        $this->io->writeError('Uninstalling plugin ' . get_class($plugin), true, IOInterface::DEBUG);
        $plugin->uninstall($this->composer, $this->io);
    }


    private function loadRepository(RepositoryInterface $repo, bool $isGlobalRepo): void
    {
        $packages = $repo->getPackages();

        $weights = [];
        foreach ($packages as $package) {
            if ($package->getType() === 'composer-plugin') {
                $extra = $package->getExtra();
                if ($package->getName() === 'composer/installers' || true === ($extra['plugin-modifies-install-path'] ?? false)) {
                    $weights[$package->getName()] = -10000;
                }
            }
        }

        $sortedPackages = PackageSorter::sortPackages($packages, $weights);
        foreach ($sortedPackages as $package) {
            if (!($package instanceof CompletePackage)) {
                continue;
            }
            if ('composer-plugin' === $package->getType()) {
                $this->registerPackage($package, false, $isGlobalRepo);

            } elseif ('composer-installer' === $package->getType()) {
                $this->registerPackage($package, false, $isGlobalRepo);
            }
        }
    }


    private function deactivateRepository(RepositoryInterface $repo, bool $isGlobalRepo): void
    {
        $packages = $repo->getPackages();
        $sortedPackages = array_reverse(PackageSorter::sortPackages($packages));

        foreach ($sortedPackages as $package) {
            if (!($package instanceof CompletePackage)) {
                continue;
            }
            if ('composer-plugin' === $package->getType()) {
                $this->deactivatePackage($package);

            } elseif ('composer-installer' === $package->getType()) {
                $this->deactivatePackage($package);
            }
        }
    }


    private function collectDependencies(InstalledRepository $installedRepo, array $collected, PackageInterface $package): array
    {
        foreach ($package->getRequires() as $requireLink) {
            foreach ($installedRepo->findPackagesWithReplacersAndProviders($requireLink->getTarget()) as $requiredPackage) {
                if (!isset($collected[$requiredPackage->getName()])) {
                    $collected[$requiredPackage->getName()] = $requiredPackage;
                    $collected = $this->collectDependencies($installedRepo, $collected, $requiredPackage);
                }
            }
        }

        return $collected;
    }


    private function getInstallPath(PackageInterface $package, bool $global = false): string
    {
        if (!$global) {
            return $this->composer->getInstallationManager()->getInstallPath($package);
        }

        assert(null !== $this->globalComposer);

        return $this->globalComposer->getInstallationManager()->getInstallPath($package);
    }


    protected function getCapabilityImplementationClassName(PluginInterface $plugin, string $capability): ?string
    {
        if (!($plugin instanceof Capable)) {
            return null;
        }

        $capabilities = (array)$plugin->getCapabilities();

        if (!empty($capabilities[$capability]) && is_string($capabilities[$capability]) && trim($capabilities[$capability])) {
            return trim($capabilities[$capability]);
        }

        if (
            array_key_exists($capability, $capabilities)
            && (empty($capabilities[$capability]) || !is_string($capabilities[$capability]) || !trim($capabilities[$capability]))
        ) {
            throw new \UnexpectedValueException('Plugin ' . get_class($plugin) . ' provided invalid capability class name(s), got ' . var_export($capabilities[$capability], true));
        }

        return null;
    }


    public function getPluginCapability(PluginInterface $plugin, $capabilityClassName, array $ctorArgs = []): ?Capability
    {
        if ($capabilityClass = $this->getCapabilityImplementationClassName($plugin, $capabilityClassName)) {
            if (!class_exists($capabilityClass)) {
                throw new \RuntimeException("Cannot instantiate Capability, as class $capabilityClass from plugin " . get_class($plugin) . " does not exist.");
            }

            $ctorArgs['plugin'] = $plugin;
            $capabilityObj = new $capabilityClass($ctorArgs);


            if (!$capabilityObj instanceof Capability || !$capabilityObj instanceof $capabilityClassName) {
                throw new \RuntimeException(
                    'Class ' . $capabilityClass . ' must implement both Composer\Plugin\Capability\Capability and ' . $capabilityClassName . '.'
                );
            }

            return $capabilityObj;
        }

        return null;
    }


    public function getPluginCapabilities($capabilityClassName, array $ctorArgs = []): array
    {
        $capabilities = [];
        foreach ($this->getPlugins() as $plugin) {
            $capability = $this->getPluginCapability($plugin, $capabilityClassName, $ctorArgs);
            if (null !== $capability) {
                $capabilities[] = $capability;
            }
        }

        return $capabilities;
    }


    private function parseAllowedPlugins($allowPluginsConfig, ?Locker $locker = null): ?array
    {
        if ([] === $allowPluginsConfig && $locker !== null && $locker->isLocked() && version_compare($locker->getPluginApi(), '2.2.0', '<')) {
            return null;
        }

        if (true === $allowPluginsConfig) {
            return ['{}' => true];
        }

        if (false === $allowPluginsConfig) {
            return ['{}' => false];
        }

        $rules = [];
        foreach ($allowPluginsConfig as $pattern => $allow) {
            $rules[BasePackage::packageNameToRegexp($pattern)] = $allow;
        }

        return $rules;
    }


    public function arePluginsDisabled($type)
    {
        return $this->disablePlugins === true || $this->disablePlugins === $type;
    }


    public function isPluginAllowed(string $package, bool $isGlobalPlugin, bool $optional = false): bool
    {
        if ($isGlobalPlugin) {
            $rules = &$this->allowGlobalPluginRules;
        } else {
            $rules = &$this->allowPluginRules;
        }


        if ($rules === null) {
            if (!$this->io->isInteractive()) {
                $this->io->writeError('<warning>For additional security you should declare the allow-plugins config with a list of packages names that are allowed to run code. See https://getcomposer.org/allow-plugins</warning>');
                $this->io->writeError('<warning>This warning will become an exception once you run composer update!</warning>');

                $rules = ['{}' => true];


                return true;
            }


            $rules = [];
        }

        foreach ($rules as $pattern => $allow) {
            if (Preg::isMatch($pattern, $package)) {
                return $allow === true;
            }
        }

        if ($package === 'composer/package-versions-deprecated') {
            return false;
        }

        if ($this->io->isInteractive()) {
            $composer = $isGlobalPlugin && $this->globalComposer !== null ? $this->globalComposer : $this->composer;

            $this->io->writeError('<warning>' . $package . ($isGlobalPlugin || $this->runningInGlobalDir ? ' (installed globally)' : '') . ' contains a Composer plugin which is currently not in your allow-plugins config. See https://getcomposer.org/allow-plugins</warning>');
            $attempts = 0;
            while (true) {


                $default = '?';
                if ($attempts > 5) {
                    $this->io->writeError('Too many failed prompts, aborting.');
                    break;
                }

                switch ($answer = $this->io->ask('Do you trust "<fg=green;options=bold>' . $package . '</>" to execute code and wish to enable it now? (writes "allow-plugins" to composer.json) [<comment>y,n,d,?</comment>] ', $default)) {
                    case 'y':
                    case 'n':
                    case 'd':
                        $allow = $answer === 'y';


                        $rules[BasePackage::packageNameToRegexp($package)] = $allow;


                        if ($answer === 'y' || $answer === 'n') {
                            $composer->getConfig()->getConfigSource()->addConfigSetting('allow-plugins.' . $package, $allow);
                        }

                        return $allow;

                    case '?':
                    default:
                        $attempts++;
                        $this->io->writeError([
                            'y - add package to allow-plugins in composer.json and let it run immediately',
                            'n - add package (as disallowed) to allow-plugins in composer.json to suppress further prompts',
                            'd - discard this, do not change composer.json and do not allow the plugin to run',
                            '? - print help',
                        ]);
                        break;
                }
            }
        } elseif ($optional) {
            return false;
        }

        throw new PluginBlockedException(
            $package . ($isGlobalPlugin || $this->runningInGlobalDir ? ' (installed globally)' : '') . ' contains a Composer plugin which is blocked by your allow-plugins config. You may add it to the list if you consider it safe.' . PHP_EOL .
            'You can run "composer ' . ($isGlobalPlugin || $this->runningInGlobalDir ? 'global ' : '') . 'config --no-plugins allow-plugins.' . $package . ' [true|false]" to enable it (true) or disable it explicitly and suppress this exception (false)' . PHP_EOL .
            'See https://getcomposer.org/allow-plugins'
        );
    }
}
