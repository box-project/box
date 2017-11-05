<?php

declare(strict_types=1);

/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

$loader = require __DIR__.'/../vendor/autoload.php';

define('BOX_PATH', realpath(__DIR__).'/../');

org\bovigo\vfs\vfsStreamWrapper::register();
