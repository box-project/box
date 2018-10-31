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

use phpseclib\Crypt\RSA;
use function pack;

/**
 * Uses the phpseclib library to verify a signature.
 *
 * @private
 */
final class PhpSecLib extends PublicKey
{
    /**
     * {@inheritdoc}
     */
    public function verify(string $signature): bool
    {
        $rsa = new RSA();
        $rsa->setSignatureMode(RSA::ENCRYPTION_PKCS1);
        $rsa->loadKey($this->getKey());

        return $rsa->verify($this->getBufferedData(), pack('H*', $signature));
    }
}
