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

/**
 * @private
 */
final readonly class ComposerArtifact
{
    public function __construct(
        public string $path,
        public array $decodedContents,
    ) {
    }

    public function toComposerJson(): ComposerJson
    {
        return new ComposerJson(
            $this->path,
            $this->decodedContents,
        );
    }

    public function toComposerLock(): ComposerLock
    {
        return new ComposerLock(
            $this->path,
            $this->decodedContents,
        );
    }
}
