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

use function array_filter;
use function array_map;

final class ComposerFiles
{
    private $composerJson;
    private $composerLock;
    private $installedJson;

    public static function createEmpty(): self
    {
        return new self(
            ComposerFile::createEmpty(),
            ComposerFile::createEmpty(),
            ComposerFile::createEmpty()
        );
    }

    public function __construct(
        ComposerFile $composerJson,
        ComposerFile $composerLock,
        ComposerFile $installedJson
    ) {
        $this->composerJson = $composerJson;
        $this->composerLock = $composerLock;
        $this->installedJson = $installedJson;
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
     * @return string[]
     */
    public function getPaths(): array
    {
        return array_filter(array_map(
            static function (ComposerFile $file): ?string {
                return $file->getPath();
            },
            [$this->composerJson, $this->composerLock, $this->installedJson]
        ));
    }
}
