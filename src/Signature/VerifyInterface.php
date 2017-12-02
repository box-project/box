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

use KevinGH\Box\Exception\Exception;
use KevinGH\Box\Exception\SignatureException;

/**
 * Defines how a signature verification class must be implemented.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
interface VerifyInterface
{
    /**
     * Initializes the hash.
     *
     * @param string $algorithm the algorithm to use
     * @param string $path      the path to the phar
     *
     * @throws Exception
     * @throws SignatureException if the hash could not be initialized
     */
    public function init($algorithm, $path);

    /**
     * Updates the hash with more data.
     *
     * @param string $data the data
     *
     * @throws Exception
     * @throws SignatureException if the hash could not be updated
     */
    public function update($data);

    /**
     * Verifies the final hash against the given signature.
     *
     * @param string $signature the signature
     *
     * @throws Exception
     * @throws SignatureException if the hash could not be verified
     *
     * @return bool TRUE if verified, FALSE if not
     */
    public function verify($signature);
}
