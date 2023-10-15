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

require_once __DIR__.'/vendor/autoload.php';

use Seld\PharUtils\Timestamps;

$file = getcwd().'/'.($argv[1] ?? '');
if (!is_file($file)) {
    echo "File does not exist.\n";
    exit(1);
}

$util = new Timestamps($file);
$util->updateTimestamps(new DateTimeImmutable('2017-10-11 08:58:00'));
$util->save($file, Phar::SHA512);
