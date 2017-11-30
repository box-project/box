<?php

namespace Herrera\Box\Signature;

use Herrera\Box\Exception\SignatureException;

/**
 * Uses the OpenSSL extension or phpseclib library to verify a signature.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class PublicKeyDelegate implements VerifyInterface
{
    /**
     * The hashing class.
     *
     * @var VerifyInterface
     */
    private $hash;

    /**
     * Selects the appropriate hashing class.
     */
    public function __construct()
    {
        if (extension_loaded('openssl')) {
            $this->hash = new OpenSsl();
        } elseif (class_exists('Crypt_RSA')) {
            $this->hash = new PhpSeclib();
        } else {
            throw SignatureException::create(
                'The "openssl" extension and "phpseclib" libraries are not available.'
            );
        }
    }

    /**
     * @see VerifyInterface::init
     */
    public function init($algorithm, $path)
    {
        $this->hash->init($algorithm, $path);
    }

    /**
     * @see VerifyInterface::update
     */
    public function update($data)
    {
        $this->hash->update($data);
    }

    /**
     * @see VerifyInterface::verify
     */
    public function verify($signature)
    {
        return $this->hash->verify($signature);
    }
}
