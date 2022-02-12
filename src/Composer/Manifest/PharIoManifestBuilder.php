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

use PharIo\Manifest\Application;
use PharIo\Manifest\ApplicationName;
use PharIo\Manifest\Author;
use PharIo\Manifest\AuthorCollection;
use PharIo\Manifest\BundledComponent;
use PharIo\Manifest\BundledComponentCollection;
use PharIo\Manifest\CopyrightInformation;
use PharIo\Manifest\Email;
use PharIo\Manifest\Library;
use PharIo\Manifest\License;
use PharIo\Manifest\Manifest;
use PharIo\Manifest\ManifestSerializer;
use PharIo\Manifest\RequirementCollection;
use PharIo\Manifest\Url;
use PharIo\Version\InvalidVersionException;
use PharIo\Version\Version;

/**
 * @author Laurent Laville
 */
class PharIoManifestBuilder implements ManifestBuilderInterface
{
    public function __invoke(array $content): string
    {
        $composerJson = $content['composer.json'];
        $installedPhp = $content['installed.php'];
        $rootPackage = $installedPhp['root'];

        $bundledComponentCollection = new BundledComponentCollection();
        foreach ($installedPhp['versions'] as $package => $values) {
            if (isset($values['pretty_version'])) {
                $version = $this->getVersion($values['pretty_version']);
                $bundledComponentCollection->add(
                    new BundledComponent(
                        $package,
                        new Version($version)
                    )
                );
            } // otherwise, it's a virtual package
        }

        switch ($rootPackage['type']) {
            case 'application':
                $type = new Application();
                break;
            case 'library':
            default:
                $type = new Library();
                break;
        }

        if (empty($rootPackage['aliases'])) {
            $version = $rootPackage['pretty_version'];
        } else {
            $version = sprintf(
                '%s@%s',
                $rootPackage['aliases'][0],
                substr($rootPackage['reference'], 0, 7)
            );
        }
        $version = $this->getVersion($version);
        $license = $composerJson['license'];

        $authorsCollection = new AuthorCollection();
        foreach ($composerJson['authors'] as $author) {
            // workaround to fix `phar-io/manifest` issue [#19](https://github.com/phar-io/manifest/issues/19)
            $authorsCollection->add(
                new Author($author['name'], new Email('john.doe@example.com')),
            );
        }

        $manifest = new Manifest(
            new ApplicationName($rootPackage['name']),
            new Version($version),
            $type,
            new CopyrightInformation(
                $authorsCollection,
                new License(
                    $license,
                    new Url(\sprintf('https://spdx.org/licenses/%s.html', $license))
                )
            ),
            new RequirementCollection(),
            $bundledComponentCollection
        );

        return (new ManifestSerializer)->serializeToString($manifest);
    }

    private function getVersion(?string $packageVersion): string
    {
        $default = '0.0.0-dev';
        //
        // @see [Branch version strings like dev-master are not supported](https://github.com/phar-io/version/issues/30)
        $version = $packageVersion ?? $default;
        try {
            new Version($version);
        } catch (InvalidVersionException $e) {
            $version = $default;
        }
        return $version;
    }
}
