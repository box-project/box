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
     *
     * @param mixed $algorithm
     * @param mixed $path
     */
    public function init($algorithm, $path): void
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
     *
     * @param mixed $data
     */
    public function update($data): void
    {
        hash_update($this->context, $data);
    }

    /**
     * @see VerifyInterface::verify
     *
     * @param mixed $signature
     */
    public function verify($signature)
    {
        return $signature === strtoupper(hash_final($this->context));
    }
}
