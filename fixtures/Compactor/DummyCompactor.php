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

use Herrera\Box\Compactor\CompactorInterface;
use KevinGH\Box\Compactor\Compactor;

class DummyCompactor implements Compactor
{
    public function __construct()
    {
    }

    /**
     * @inheritdoc
     */
    public function compact(string $contents): string
    {
    }

    /**
     * @inheritdoc
     */
    public function supports(string $file): bool
    {
    }
}
