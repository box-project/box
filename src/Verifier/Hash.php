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
use KevinGH\Box\Verifier;
use function hash_algos;
use function hash_final;
use function hash_init;
use function hash_update;
use function implode;
use function preg_replace;
use function strtolower;
use function strtoupper;

/**
 * Uses the PHP hash library to verify a signature.
 *
 * @private
 */
final class Hash implements Verifier
{
    /**
     * The hash context.
     *
     * @var resource
     */
    private $context;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $algorithm, string $path)
    {
        $algorithm = strtolower(
            preg_replace(
                '/[^A-Za-z0-9]+/',
                '',
                $algorithm
            )
        );

        Assertion::inArray(
            $algorithm,
            hash_algos(),
            'Expected %s to be a known algorithm: "'
            .implode('", "', hash_algos())
            .'"'
        );

        $this->context = hash_init($algorithm);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $data): void
    {
        Assertion::notNull($this->context, 'Expected to be initialised before being updated.');

        hash_update($this->context, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function verify(string $signature): bool
    {
        return $signature === strtoupper(hash_final($this->context));
    }
}
