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

use KevinGH\Box\PhpScoper\Scoper;
use Throwable;

/**
 * @private
 */
final class PhpScoper implements Compactor
{
    private $scoper;

    public function __construct(Scoper $scoper)
    {
        $this->scoper = $scoper;
    }

    /**
     * {@inheritdoc}
     */
    public function compact(string $file, string $contents): string
    {
        try {
            return $this->scoper->scope($file, $contents);
        } catch (Throwable $throwable) {
            return $contents;
        }
    }

    public function getScoper(): Scoper
    {
        return $this->scoper;
    }
}
