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

interface Compactor
{
    /**
     * Compacts the file contents.
     *
     * @param string $contents The file contents
     *
     * @return string The compacted contents
     */
    public function compact(string $contents): string;

    /**
     * Checks if the file is supported.
     *
     * @param string $file the file name
     *
     * @return bool
     */
    public function supports(string $file): bool;
}
