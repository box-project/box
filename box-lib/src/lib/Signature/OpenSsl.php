<?php

namespace Herrera\Box\Signature;

use Herrera\Box\Exception\OpenSslException;

/**
 * Uses OpenSSL to verify the signature.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class OpenSsl extends AbstractPublicKey
{
    /**
     * @see VerifyInterface::verify
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
        } elseif (!empty($error)) {
            throw new OpenSslException($error);
        }

        return (1 === $result);
    }
}
