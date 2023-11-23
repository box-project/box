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

namespace KevinGH\Box\Compactor;

use function serialize;
use function unserialize;

/**
 * Base compactor class providing a slightly simpler API to compact the content only if the file is supported.
 *
 * @private
 */
final class NullCompactor implements Compactor
{
    public function compact(string $file, string $contents): string
    {
        return $contents;
    }

    public function serialize(): string
    {
        return serialize($this);
    }

    public static function unserialize(string $serializedCompactor): static
    {
        return unserialize($serializedCompactor, ['allowed_classes' => [self::class]]);
    }
}
