<?php

namespace Herrera\Box\Signature;

use Herrera\Box\Exception\SignatureException;

/**
 * Uses the PHP hash library to verify a signature.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Hash implements VerifyInterface
{
    /**
     * The hash context.
     *
     * @var resource
     */
    private $context;

    /**
     * @see VerifyInterface::init
     */
    public function init($algorithm, $path)
    {
        $algorithm = strtolower(
            preg_replace(
                '/[^A-Za-z0-9]+/',
                '',
                $algorithm
            )
        );

        if (false === ($this->context = @hash_init($algorithm))) {
            $this->context = null;

            throw SignatureException::lastError();
        }
    }

    /**
     * @see VerifyInterface::update
     */
    public function update($data)
    {
        hash_update($this->context, $data);
    }

    /**
     * @see VerifyInterface::verify
     */
    public function verify($signature)
    {
        return ($signature === strtoupper(hash_final($this->context)));
    }
}
