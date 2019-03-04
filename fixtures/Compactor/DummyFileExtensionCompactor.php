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

use function func_get_args;
use KevinGH\Box\NotCallable;

class DummyFileExtensionCompactor extends FileExtensionCompactor
{
    use NotCallable;

    /**
     * {@inheritdoc}
     */
    protected function compactContent(string $contents): string
    {
        $this->__call(__METHOD__, func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    protected function supports(string $file): bool
    {
        $this->__call(__METHOD__, func_get_args());
    }
}
