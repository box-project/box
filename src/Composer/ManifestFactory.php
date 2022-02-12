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

namespace KevinGH\Box\Composer;

use KevinGH\Box\Composer\Manifest\ManifestBuilderInterface;
use KevinGH\Box\Configuration\Configuration;
use function array_key_exists;
use function class_exists;
use function file_exists;
use function implode;
use function is_readable;
use function KevinGH\Box\FileSystem\make_path_absolute;

/**
 * @author Laurent Laville
 */
final class ManifestFactory
{
    public static function create(Configuration $config): ?string
    {
        $metadata = $config->getMetadata();
        if (!class_exists($metadata)) {
            // Class defined by "metadata" config parameter does not exist, or is not readable by Composer Autoloader
            return null;
        }
        $builder = new $metadata;
        if (!$builder instanceof ManifestBuilderInterface) {
            // Your manifest class builder is not compatible.
            return null;
        }

        // The composer.lock and installed.php are optional (e.g. if there is no dependencies installed)
        // but when one is present, the other must be as well
        $decodedJsonContents = $config->getDecodedComposerJsonContents();

        $composerLock = $config->getComposerLock();
        if (null === $composerLock) {
            // No dependencies installed
            $installedPhp = [];
        } else {
            $normalizePath = function ($file, $basePath) {
                return make_path_absolute(trim($file), $basePath);
            };

            $basePath = $config->getBasePath();

            if (null !== $decodedJsonContents && array_key_exists('vendor-dir', $decodedJsonContents)) {
                $vendorDir = $normalizePath($decodedJsonContents['vendor-dir'], $basePath);
            } else {
                $vendorDir = $normalizePath('vendor', $basePath);
            }

            $file = implode(DIRECTORY_SEPARATOR, [$vendorDir, 'composer', 'installed.php']);
            if (!file_exists($file) || !is_readable($file)) {
                return null;
            }
            $installedPhp = include $file;
        }

        return $builder(
            [
                'composer.json' => $decodedJsonContents,
                'installed.php' => (array) $installedPhp,
            ]
        );
    }
}
