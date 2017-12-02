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

use KevinGH\Box\Exception\FileException;

/**
 * Loads the private key from a file to use for verification.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
abstract class AbstractPublicKey extends AbstractBufferedHash
{
    /**
     * The private key.
     *
     * @var string
     */
    private $key;

    /**
     * @see VerifyInterface::init
     *
     * @param mixed $algorithm
     * @param mixed $path
     */
    public function init($algorithm, $path): void
    {
        if (false === ($this->key = @file_get_contents($path.'.pubkey'))) {
            throw FileException::lastError();
        }
    }

    /**
     * Returns the private key.
     *
     * @return string the private key
     */
    protected function getKey()
    {
        return $this->key;
    }
}
