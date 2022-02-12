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

namespace KevinGH\Box\Composer\Manifest;

use function implode;
use function sprintf;
use function substr;
use const PHP_EOL;

/**
 * @author Laurent Laville
 */
class SimpleTextManifestBuilder implements ManifestBuilderInterface
{
    public function __invoke(array $content): string
    {
        $installedPhp = $content['installed.php'];
        $rootPackage = $installedPhp['root'];
        $entries = [];

        if (empty($rootPackage['aliases'])) {
            $version = $rootPackage['pretty_version'];
        } else {
            $version = sprintf(
                '%s@%s',
                $rootPackage['aliases'][0],
                substr($rootPackage['reference'], 0, 7)
            );
        }

        $entries[] = sprintf('%s: %s', $rootPackage['name'], $version);

        foreach ($installedPhp['versions'] as $package => $values) {
            if (isset($values['pretty_version'])) {
                $entries[] = sprintf('%s: %s', $package, $values['pretty_version']);
            } // otherwise, it's a virtual package
        }

        return implode(PHP_EOL, $entries);
    }
}