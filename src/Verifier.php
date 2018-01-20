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

namespace KevinGH\Box;

use KevinGH\Box\Exception\SignatureException;

/**
 * Defines how a signature verification class must be implemented.
 */
interface Verifier
{
    /**
     * Initializes the hash.
     *
     * @param string $algorithm The algorithm to use
     * @param string $path      The path to the PHAR
     *
     * @throws SignatureException The hash could not be initialized
     */
    public function __construct(string $algorithm, string $path);

    /**
     * Updates the hash with more data.
     *
     * @param string $data
     *
     * @throws SignatureException The hash could not be updated
     */
    public function update(string $data): void;

    /**
     * Verifies the final hash against the given signed PHAR.
     *
     * @param string $signature
     *
     * @throws SignatureException The hash could not be verified
     *
     * @return bool true if verified, false if not
     */
    public function verify(string $signature): bool;
}
