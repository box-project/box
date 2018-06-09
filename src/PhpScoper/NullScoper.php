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

use Humbug\PhpScoper\Whitelist;

final class NullScoper implements Scoper
{
    /**
     * {@inheritdoc}
     */
    public function scope(string $filePath, string $contents): string
    {
        return $contents;
    }

    /**
     * @return string[]
     */
    public function getWhitelist(): Whitelist
    {
        return Whitelist::create(true);
    }

    public function getPrefix(): string
    {
        return '';
    }
}
