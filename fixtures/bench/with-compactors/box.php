#!/usr/bin/env php
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

namespace BenchTest;

use BenchTest\Console\Application;
use BenchTest\Console\OutputFormatterConfigurator;
use Fidry\Console\Application\ApplicationRunner;
use Fidry\Console\IO;
use RuntimeException;
use function file_exists;
use function in_array;
use const PHP_EOL;
use const PHP_SAPI;

// See https://github.com/easysoft/phpmicro for the micro SAPI.
if (false === in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed', 'micro'], true)) {
    echo PHP_EOL.'Box may only be invoked from a command line, got "'.PHP_SAPI.'"'.PHP_EOL;

    exit(1);
}

(static function (): void {
    if (file_exists($autoload = __DIR__.'/../../../autoload.php')) {
        // Is installed via Composer
        include_once $autoload;

        return;
    }

    if (file_exists($autoload = __DIR__.'/../vendor/autoload.php')) {
        // Is installed locally
        include_once $autoload;

        return;
    }

    throw new RuntimeException('Unable to find the Composer autoloader.');
})();

register_aliases();
register_error_handler();

$io = IO::createDefault();
OutputFormatterConfigurator::configure($io);

$runner = new ApplicationRunner(new Application());
$runner->run($io);
