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

use phpseclib\Crypt\RSA;

/**
 * Uses the phpseclib library to verify a signature.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class PhpSecLib extends AbstractPublicKey
{
    /**
     * @see VerifyInterface::verify
     *
     * @param mixed $signature
     */
    public function verify($signature)
    {
        $rsa = new RSA();
        $rsa->setSignatureMode(RSA::ENCRYPTION_PKCS1);
        $rsa->loadKey($this->getKey());

        return $rsa->verify($this->getData(), pack('H*', $signature));
    }
}
