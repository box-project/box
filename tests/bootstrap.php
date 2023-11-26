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
use Symfony\Component\Filesystem\Path;
use function KevinGH\Box\register_aliases;
use function Safe\putenv;

register_aliases();

vfsStreamWrapper::register();

$binBoxPath = Path::normalize(__DIR__.'/../bin/box');

$_SERVER['BOX_BIN'] = $_ENV['BOX_BIN'] = $binBoxPath;
putenv('BOX_BIN='.$binBoxPath);
// Some tests depend on timezone: https://github.com/php/php-src/issues/12532
putenv('TZ=GMT-2');
