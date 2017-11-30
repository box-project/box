<?php

define('RES_DIR', __DIR__ . '/../../res');

$loader = require __DIR__ . '/../../../vendor/autoload.php';
$loader->add(null, __DIR__);

org\bovigo\vfs\vfsStreamWrapper::register();

class_alias(\PHPUnit\Framework\TestCase::class, \PHPUnit_Framework_TestCase::class);
