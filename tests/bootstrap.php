<?php

$loader = require __DIR__ . '/../vendor/autoload.php';

define('BOX_PATH', realpath(__DIR__) . '/../');

org\bovigo\vfs\vfsStreamWrapper::register();
