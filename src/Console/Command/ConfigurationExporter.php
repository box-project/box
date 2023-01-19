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

namespace KevinGH\Box\Console\Command;

use DateTimeImmutable;
use DateTimeZone;
use KevinGH\Box\Configuration\Configuration;
use KevinGH\Box\NotInstantiable;
use function function_exists;
use function get_loaded_extensions;
use function implode;
use function KevinGH\Box\get_box_version;
use function php_uname;
use const DATE_ATOM;
use const PHP_OS;
use const PHP_VERSION;

final class ConfigurationExporter
{
    use NotInstantiable;

    public static function export(Configuration $config): string
    {
        $date = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
        $file = $config->getConfigurationFile() ?? 'No config file';

        $phpVersion = PHP_VERSION;
        $phpExtensions = implode(',', get_loaded_extensions());
        $os = function_exists('php_uname') ? PHP_OS.' / '.php_uname('r') : 'Unknown OS';
        $command = implode(' ', $GLOBALS['argv']);
        $boxVersion = get_box_version();

        $header = <<<EOF
            //
            // Processed content of the configuration file "{$file}" dumped for debugging purposes
            //
            // PHP Version: {$phpVersion}
            // PHP extensions: {$phpExtensions}
            // OS: {$os}
            // Command: {$command}
            // Box: {$boxVersion}
            // Time: {$date}
            //


            EOF;

        return $header.$config->export();
    }
}
