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

use KevinGH\Box\Verifier;
use RuntimeException;
use function class_exists;
use function extension_loaded;

/**
 * Uses the OpenSSL extension or phpseclib library to verify a signed PHAR.
 *
 * @private
 */
final class PublicKeyDelegate implements Verifier
{
    private $hash;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $signature, string $path)
    {
        if (extension_loaded('openssl')) {
            $this->hash = new OpenSsl($signature, $path);
        } elseif (class_exists('Crypt_RSA')) {
            $this->hash = new PhpSeclib($signature, $path);
        } else {
            throw new RuntimeException('The "openssl" extension and "phpseclib" libraries are not available.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $data): void
    {
        $this->hash->update($data);
    }

    /**
     * {@inheritdoc}
     */
    public function verify(string $signature): bool
    {
        return $this->hash->verify($signature);
    }
}
