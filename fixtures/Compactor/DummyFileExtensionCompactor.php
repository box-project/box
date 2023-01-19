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

use KevinGH\Box\UnsupportedMethodCall;

class DummyFileExtensionCompactor extends FileExtensionCompactor
{
    protected function compactContent(string $contents): string
    {
        throw UnsupportedMethodCall::forMethod(__CLASS__, __METHOD__);
    }

    protected function supports(string $file): bool
    {
        throw UnsupportedMethodCall::forMethod(__CLASS__, __METHOD__);
    }
}
