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

final readonly class ComposerArtifacts
{
    public function __construct(
        public ?ComposerArtifact $composerJson = null,
        public ?ComposerArtifact $composerLock = null,
        public ?ComposerArtifact $installedJson = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getPaths(): array
    {
        return array_values(
            array_filter(
                array_map(
                    static fn (?ComposerArtifact $file): ?string => $file?->path,
                    [$this->composerJson, $this->composerLock, $this->installedJson],
                ),
            ),
        );
    }
}
