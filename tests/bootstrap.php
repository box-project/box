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

use org\bovigo\vfs\vfsStreamWrapper;
use function KevinGH\Box\register_aliases;

$loader = require __DIR__.'/../vendor/autoload.php';

vfsStreamWrapper::register();

register_aliases();
