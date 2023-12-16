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

namespace KevinGH\Box\Composer\Artifact;

use function array_filter;
use function array_map;
use function array_values;

final readonly class ComposerFiles
{
    public function __construct(
        private ?DecodedComposerJson $composerJson = null,
        private ?DecodedComposerLock $composerLock = null,
        private ?ComposerFile $installedJson = null,
    ) {
    }

    public function getComposerJson(): ?DecodedComposerJson
    {
        return $this->composerJson;
    }

    public function getComposerLock(): ?DecodedComposerLock
    {
        return $this->composerLock;
    }

    public function getInstalledJson(): ?ComposerFile
    {
        return $this->installedJson;
    }

    /**
     * @return list<string>
     */
    public function getPaths(): array
    {
        return array_values(
            array_filter(
                array_map(
                    static fn (null|ComposerFile|DecodedComposerJson|DecodedComposerLock $file): ?string => $file?->path,
                    [$this->composerJson, $this->composerLock, $this->installedJson],
                ),
            ),
        );
    }
}
