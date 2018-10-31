<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitb86e8e865c7b52991febec3f1783f755
{
    public static $prefixLengthsPsr4 = array (
        '_' => 
        array (
            '_HumbugBoxd1e70270db87\\KevinGH\\RequirementChecker\\' => 50,
            '_HumbugBoxd1e70270db87\\Composer\\Semver\\' => 39,
        ),
    );

    public static $prefixDirsPsr4 = array (
        '_HumbugBoxd1e70270db87\\KevinGH\\RequirementChecker\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
        '_HumbugBoxd1e70270db87\\Composer\\Semver\\' => 
        array (
            0 => __DIR__ . '/..' . '/composer/semver/src',
        ),
    );

    public static $classMap = array (
        '_HumbugBoxd1e70270db87\\Composer\\Semver\\Comparator' => __DIR__ . '/..' . '/composer/semver/src/Comparator.php',
        '_HumbugBoxd1e70270db87\\Composer\\Semver\\Constraint\\AbstractConstraint' => __DIR__ . '/..' . '/composer/semver/src/Constraint/AbstractConstraint.php',
        '_HumbugBoxd1e70270db87\\Composer\\Semver\\Constraint\\Constraint' => __DIR__ . '/..' . '/composer/semver/src/Constraint/Constraint.php',
        '_HumbugBoxd1e70270db87\\Composer\\Semver\\Constraint\\ConstraintInterface' => __DIR__ . '/..' . '/composer/semver/src/Constraint/ConstraintInterface.php',
        '_HumbugBoxd1e70270db87\\Composer\\Semver\\Constraint\\EmptyConstraint' => __DIR__ . '/..' . '/composer/semver/src/Constraint/EmptyConstraint.php',
        '_HumbugBoxd1e70270db87\\Composer\\Semver\\Constraint\\MultiConstraint' => __DIR__ . '/..' . '/composer/semver/src/Constraint/MultiConstraint.php',
        '_HumbugBoxd1e70270db87\\Composer\\Semver\\Semver' => __DIR__ . '/..' . '/composer/semver/src/Semver.php',
        '_HumbugBoxd1e70270db87\\Composer\\Semver\\VersionParser' => __DIR__ . '/..' . '/composer/semver/src/VersionParser.php',
        '_HumbugBoxd1e70270db87\\KevinGH\\RequirementChecker\\Checker' => __DIR__ . '/../..' . '/src/Checker.php',
        '_HumbugBoxd1e70270db87\\KevinGH\\RequirementChecker\\IO' => __DIR__ . '/../..' . '/src/IO.php',
        '_HumbugBoxd1e70270db87\\KevinGH\\RequirementChecker\\IsExtensionFulfilled' => __DIR__ . '/../..' . '/src/IsExtensionFulfilled.php',
        '_HumbugBoxd1e70270db87\\KevinGH\\RequirementChecker\\IsFulfilled' => __DIR__ . '/../..' . '/src/IsFulfilled.php',
        '_HumbugBoxd1e70270db87\\KevinGH\\RequirementChecker\\IsPhpVersionFulfilled' => __DIR__ . '/../..' . '/src/IsPhpVersionFulfilled.php',
        '_HumbugBoxd1e70270db87\\KevinGH\\RequirementChecker\\Printer' => __DIR__ . '/../..' . '/src/Printer.php',
        '_HumbugBoxd1e70270db87\\KevinGH\\RequirementChecker\\Requirement' => __DIR__ . '/../..' . '/src/Requirement.php',
        '_HumbugBoxd1e70270db87\\KevinGH\\RequirementChecker\\RequirementCollection' => __DIR__ . '/../..' . '/src/RequirementCollection.php',
        '_HumbugBoxd1e70270db87\\KevinGH\\RequirementChecker\\Terminal' => __DIR__ . '/../..' . '/src/Terminal.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitb86e8e865c7b52991febec3f1783f755::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitb86e8e865c7b52991febec3f1783f755::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitb86e8e865c7b52991febec3f1783f755::$classMap;

        }, null, ClassLoader::class);
    }
}
