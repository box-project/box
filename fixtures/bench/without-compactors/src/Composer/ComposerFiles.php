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

namespace BenchTest\Composer;

use function array_filter;
use function array_map;
use function array_values;

final class ComposerFiles
{
    public static function createEmpty(): self
    {
        return new self(
            ComposerFile::createEmpty(),
            ComposerFile::createEmpty(),
            ComposerFile::createEmpty(),
        );
    }

    public function __construct(
        private readonly ComposerFile $composerJson,
        private readonly ComposerFile $composerLock,
        private readonly ComposerFile $installedJson,
    ) {
    }

    public function getComposerJson(): ComposerFile
    {
        return $this->composerJson;
    }

    public function getComposerLock(): ComposerFile
    {
        return $this->composerLock;
    }

    public function getInstalledJson(): ComposerFile
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
                    static fn (ComposerFile $file): ?string => $file->getPath(),
                    [$this->composerJson, $this->composerLock, $this->installedJson],
                ),
            ),
        );
    }
}
