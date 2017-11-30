<?php

namespace Herrera\Box\Signature;

use Herrera\Box\Exception\FileException;

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
     */
    public function init($algorithm, $path)
    {
        if (false === ($this->key = @file_get_contents($path . '.pubkey'))) {
            throw FileException::lastError();
        }
    }

    /**
     * Returns the private key.
     *
     * @return string The private key.
     */
    protected function getKey()
    {
        return $this->key;
    }
}
