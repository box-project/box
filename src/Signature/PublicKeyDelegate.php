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

use KevinGH\Box\Exception\SignatureException;

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
     *
     * @param mixed $algorithm
     * @param mixed $path
     */
    public function init($algorithm, $path): void
    {
        $this->hash->init($algorithm, $path);
    }

    /**
     * @see VerifyInterface::update
     *
     * @param mixed $data
     */
    public function update($data): void
    {
        $this->hash->update($data);
    }

    /**
     * @see VerifyInterface::verify
     *
     * @param mixed $signature
     */
    public function verify($signature)
    {
        return $this->hash->verify($signature);
    }
}
