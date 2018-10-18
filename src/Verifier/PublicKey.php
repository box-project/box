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

use Assert\Assertion;
use function KevinGH\Box\FileSystem\file_contents;

/**
 * Loads the private key from a file to use for verification.
 *
 * @private
 */
abstract class PublicKey extends BufferedHash
{
    /** @var string The private key */
    private $key;

    /**
     * {@inheritdoc}
     */
    final public function __construct(string $algorithm, string $path)
    {
        $keyPath = $path.'.pubkey';

        Assertion::file($keyPath);
        Assertion::readable($keyPath);

        $this->key = file_contents($keyPath);
    }

    /**
     * {@inheritdoc}
     */
    final protected function getKey(): string
    {
        return $this->key;
    }
}
