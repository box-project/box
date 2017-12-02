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

namespace KevinGH\Box\Signature;

use KevinGH\Box\Exception\OpenSslException;

/**
 * Uses OpenSSL to verify the signature.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class OpenSsl extends AbstractPublicKey
{
    /**
     * @see VerifyInterface::verify
     *
     * @param mixed $signature
     */
    public function verify($signature)
    {
        OpenSslException::reset();

        ob_start();

        $result = openssl_verify(
            $this->getData(),
            @pack('H*', $signature),
            $this->getKey()
        );

        $error = trim(ob_get_clean());

        if (-1 === $result) {
            throw OpenSslException::lastError();
        }
        if (!empty($error)) {
            throw new OpenSslException($error);
        }

        return 1 === $result;
    }
}
