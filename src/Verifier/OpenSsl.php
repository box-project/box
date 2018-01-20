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

use KevinGH\Box\Exception\OpenSslExceptionFactory;
use RuntimeException;

/**
 * Uses OpenSSL to verify the signature.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
final class OpenSsl extends PublicKey
{
    /**
     * {@inheritdoc}
     */
    public function verify(string $signature): bool
    {
        OpenSslExceptionFactory::reset();

        ob_start();

        $result = openssl_verify(
            $this->getBufferedData(),
            @pack('H*', $signature),
            $this->getKey()
        );

        $error = trim(ob_get_clean());

        if (-1 === $result) {
            throw OpenSslExceptionFactory::createForLastError();
        }
        if (!empty($error)) {
            throw new RuntimeException($error);
        }

        return 1 === $result;
    }
}
