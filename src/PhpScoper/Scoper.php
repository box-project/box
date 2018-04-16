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

namespace KevinGH\Box\PhpScoper;

interface Scoper
{
    /**
     * Scope AKA. apply the given prefix to the file in the appropriate way.
     *
     * @param string $filePath File to scope
     * @param string $contents File contents
     *
     * @return string Contents of the file with the prefix applied
     */
    public function scope(string $filePath, string $contents): string;

    /**
     * @return string[]
     */
    public function getWhitelist(): array;

    public function getPrefix(): string;
}
