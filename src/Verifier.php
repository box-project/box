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

/**
 * Defines how a signature verification class must be implemented.
 *
 * @private
 */
interface Verifier
{
    /**
     * Initializes the hash.
     *
     * @param string $algorithm The algorithm to use
     * @param string $path      The path to the PHAR
     */
    public function __construct(string $algorithm, string $path);

    /**
     * Updates the hash with more data.
     */
    public function update(string $data): void;

    /**
     * Verifies the final hash against the given signed PHAR.
     *
     * @return bool true if verified, false if not
     */
    public function verify(string $signature): bool;
}
