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

use Webmozart\Assert\Assert;
use function is_array;
use function is_string;

class ComposerFile
{
    private ?string $path;
    private string $contents;
    private array $decodedContents;

    final public static function createEmpty(): self
    {
        return new self(null, []);
    }

    public function __construct(?string $path, array|string $contents)
    {
        Assert::nullOrNotEmpty($path);

        if (null === $path) {
            Assert::same([], $contents);
        }

        $this->path = $path;
        $this->contents = is_string($contents) ? $contents : '';
        $this->decodedContents = is_array($contents) ? $contents : [];
    }

    final public function getPath(): ?string
    {
        return $this->path;
    }

    final public function getContents(): string
    {
        return $this->contents;
    }

    final public function getDecodedContents(): array
    {
        return $this->decodedContents;
    }
}
