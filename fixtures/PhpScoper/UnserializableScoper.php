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

use Humbug\PhpScoper\Scoper\Scoper as PhpScoperScoper;
use KevinGH\Box\UnsupportedMethodCall;
use Serializable;

final class UnserializableScoper implements PhpScoperScoper, Serializable
{
    public function scope(string $filePath, string $contents): string
    {
        throw UnsupportedMethodCall::forMethod(__CLASS__, __METHOD__);
    }

    public function serialize(): string
    {
        throw UnsupportedMethodCall::forMethod(__CLASS__, __METHOD__);
    }

    public function unserialize($serialized): void
    {
        throw UnsupportedMethodCall::forMethod(__CLASS__, __METHOD__);
    }
}
