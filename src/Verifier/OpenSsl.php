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

namespace KevinGH\Box\Verifier;

use function openssl_verify;
use function pack;

/**
 * Uses OpenSSL to verify the signature.
 *
 * @private
 */
final class OpenSsl extends PublicKey
{
    /**
     * {@inheritdoc}
     */
    public function verify(string $signature): bool
    {
        $result = openssl_verify(
            $this->getBufferedData(),
            @pack('H*', $signature),
            $this->getKey()
        );

        return 1 === $result;
    }
}
