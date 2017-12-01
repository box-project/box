<?php

namespace KevinGH\Box\Signature;

use Crypt_RSA;
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
     */
    public function verify($signature)
    {
        $rsa = new RSA();
        $rsa->setSignatureMode(RSA::ENCRYPTION_PKCS1);
        $rsa->loadKey($this->getKey());

        return $rsa->verify($this->getData(), pack('H*', $signature));
    }
}
