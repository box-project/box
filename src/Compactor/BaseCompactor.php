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

/**
 * Base compactor class providing a slightly simpler API to compact the content only if the file is supported.
 *
 * @private
 */
abstract class BaseCompactor implements Compactor
{
    public function compact(string $file, string $contents): string
    {
        if ($this->supports($file)) {
            return $this->compactContent($contents);
        }

        return $contents;
    }

    abstract protected function compactContent(string $contents): string;

    abstract protected function supports(string $file): bool;
}
