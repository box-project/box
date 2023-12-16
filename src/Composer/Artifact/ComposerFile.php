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

use Webmozart\Assert\Assert;

final readonly class ComposerFile
{
    public static function createEmpty(): self
    {
        return new self(null, []);
    }

    public function __construct(
        public ?string $path,
        public array $decodedContents,
    ) {
        Assert::nullOrNotEmpty($path);

        if (null === $path) {
            Assert::same([], $decodedContents);
        }
    }
}
