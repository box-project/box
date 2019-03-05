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

use Assert\Assertion;

final class ComposerFile
{
    private $path;
    private $contents;

    public static function createEmpty(): self
    {
        return new self(null, []);
    }

    public function __construct(?string $path, array $contents)
    {
        Assertion::nullOrNotEmpty($path);

        if (null === $path) {
            Assertion::same([], $contents);
        }

        $this->path = $path;
        $this->contents = $contents;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getDecodedContents(): array
    {
        return $this->contents;
    }
}
