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

namespace BenchTest\Compactor;

/**
 * A compactor is a class called to process a file contents before adding it to the PHAR. This make it possible to for
 * example strip down the file from useless phpdoc.
 *
 * @private
 */
interface Compactor
{
    /**
     * Compacts the file contents.
     *
     * @param string $file     The file name
     * @param string $contents The file contents
     *
     * @return string The compacted contents
     */
    public function compact(string $file, string $contents): string;
}
