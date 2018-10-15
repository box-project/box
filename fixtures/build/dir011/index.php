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

use function Foo\back;

require __DIR__.'/vendor/autoload.php';

salute();
echo ' ';
X::salute();
back();
echo PHP_EOL;
