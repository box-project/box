<?php

namespace HumbugBox465\Composer;

use HumbugBox465\Composer\Autoload\ClassLoader;
use HumbugBox465\Composer\Semver\VersionParser;
class InstalledVersions
{
    private static $selfDir = null;
    /**
    @psalm-var
    */
    private static $installed;
    private static $installedIsLocalDir;
    private static $canGetVendors;
    /**
    @psalm-var
    */
    private static $installedByVendor = array();
    /**
    @psalm-return
    */
    public static function getInstalledPackages()
    {
        $packages = array();
        foreach (self::getInstalled() as $installed) {
            $packages[] = array_keys($installed['versions']);
        }
        if (1 === \count($packages)) {
            return $packages[0];
        }
        return array_keys(array_flip(\call_user_func_array('array_merge', $packages)));
    }
    /**
    @psalm-return
    */
    public static function getInstalledPackagesByType($type)
    {
        $packagesByType = array();
        foreach (self::getInstalled() as $installed) {
            foreach ($installed['versions'] as $name => $package) {
                if (isset($package['type']) && $package['type'] === $type) {
                    $packagesByType[] = $name;
                }
            }
        }
        return $packagesByType;
    }
    public static function isInstalled($packageName, $includeDevRequirements = \true)
    {
        foreach (self::getInstalled() as $installed) {
            if (isset($installed['versions'][$packageName])) {
                return $includeDevRequirements || !isset($installed['versions'][$packageName]['dev_requirement']) || $installed['versions'][$packageName]['dev_requirement'] === \false;
            }
        }
        return \false;
    }
    public static function satisfies(VersionParser $parser, $packageName, $constraint)
    {
        $constraint = $parser->parseConstraints((string) $constraint);
        $provided = $parser->parseConstraints(self::getVersionRanges($packageName));
        return $provided->matches($constraint);
    }
    public static function getVersionRanges($packageName)
    {
        foreach (self::getInstalled() as $installed) {
            if (!isset($installed['versions'][$packageName])) {
                continue;
            }
            $ranges = array();
            if (isset($installed['versions'][$packageName]['pretty_version'])) {
                $ranges[] = $installed['versions'][$packageName]['pretty_version'];
            }
            if (array_key_exists('aliases', $installed['versions'][$packageName])) {
                $ranges = array_merge($ranges, $installed['versions'][$packageName]['aliases']);
            }
            if (array_key_exists('replaced', $installed['versions'][$packageName])) {
                $ranges = array_merge($ranges, $installed['versions'][$packageName]['replaced']);
            }
            if (array_key_exists('provided', $installed['versions'][$packageName])) {
                $ranges = array_merge($ranges, $installed['versions'][$packageName]['provided']);
            }
            return implode(' || ', $ranges);
        }
        throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
    }
    public static function getVersion($packageName)
    {
        foreach (self::getInstalled() as $installed) {
            if (!isset($installed['versions'][$packageName])) {
                continue;
            }
            if (!isset($installed['versions'][$packageName]['version'])) {
                return null;
            }
            return $installed['versions'][$packageName]['version'];
        }
        throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
    }
    public static function getPrettyVersion($packageName)
    {
        foreach (self::getInstalled() as $installed) {
            if (!isset($installed['versions'][$packageName])) {
                continue;
            }
            if (!isset($installed['versions'][$packageName]['pretty_version'])) {
                return null;
            }
            return $installed['versions'][$packageName]['pretty_version'];
        }
        throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
    }
    public static function getReference($packageName)
    {
        foreach (self::getInstalled() as $installed) {
            if (!isset($installed['versions'][$packageName])) {
                continue;
            }
            if (!isset($installed['versions'][$packageName]['reference'])) {
                return null;
            }
            return $installed['versions'][$packageName]['reference'];
        }
        throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
    }
    public static function getInstallPath($packageName)
    {
        foreach (self::getInstalled() as $installed) {
            if (!isset($installed['versions'][$packageName])) {
                continue;
            }
            return isset($installed['versions'][$packageName]['install_path']) ? $installed['versions'][$packageName]['install_path'] : null;
        }
        throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
    }
    /**
    @psalm-return
    */
    public static function getRootPackage()
    {
        $installed = self::getInstalled();
        return $installed[0]['root'];
    }
    /**
    @psalm-return
    */
    public static function getRawData()
    {
        @trigger_error('getRawData only returns the first dataset loaded, which may not be what you expect. Use getAllRawData() instead which returns all datasets for all autoloaders present in the process.', \E_USER_DEPRECATED);
        if (null === self::$installed) {
            if (substr(__DIR__, -8, 1) !== 'C') {
                self::$installed = include __DIR__ . '/installed.php';
            } else {
                self::$installed = array();
            }
        }
        return self::$installed;
    }
    /**
    @psalm-return
    */
    public static function getAllRawData()
    {
        return self::getInstalled();
    }
    /**
    @psalm-param
    */
    public static function reload($data)
    {
        self::$installed = $data;
        self::$installedByVendor = array();
        self::$installedIsLocalDir = \false;
    }
    private static function getSelfDir()
    {
        if (self::$selfDir === null) {
            self::$selfDir = strtr(__DIR__, '\\', '/');
        }
        return self::$selfDir;
    }
    /**
    @psalm-return
    */
    private static function getInstalled()
    {
        if (null === self::$canGetVendors) {
            self::$canGetVendors = method_exists('HumbugBox465\Composer\Autoload\ClassLoader', 'getRegisteredLoaders');
        }
        $installed = array();
        $copiedLocalDir = \false;
        if (self::$canGetVendors) {
            $selfDir = self::getSelfDir();
            foreach (ClassLoader::getRegisteredLoaders() as $vendorDir => $loader) {
                $vendorDir = strtr($vendorDir, '\\', '/');
                if (isset(self::$installedByVendor[$vendorDir])) {
                    $installed[] = self::$installedByVendor[$vendorDir];
                } elseif (is_file($vendorDir . '/composer/installed.php')) {
                    $required = require $vendorDir . '/composer/installed.php';
                    self::$installedByVendor[$vendorDir] = $required;
                    $installed[] = $required;
                    if (self::$installed === null && $vendorDir . '/composer' === $selfDir) {
                        self::$installed = $required;
                        self::$installedIsLocalDir = \true;
                    }
                }
                if (self::$installedIsLocalDir && $vendorDir . '/composer' === $selfDir) {
                    $copiedLocalDir = \true;
                }
            }
        }
        if (null === self::$installed) {
            if (substr(__DIR__, -8, 1) !== 'C') {
                $required = require __DIR__ . '/installed.php';
                self::$installed = $required;
            } else {
                self::$installed = array();
            }
        }
        if (self::$installed !== array() && !$copiedLocalDir) {
            $installed[] = self::$installed;
        }
        return $installed;
    }
}
