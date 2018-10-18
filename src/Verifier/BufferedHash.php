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

/**
 * Buffers the hash as opposed to updating incrementally.
 *
 * @private
 */
abstract class BufferedHash implements Verifier
{
    /** @var string The buffered data */
    private $data;

    /**
     * {@inheritdoc}
     */
    final public function update(string $data): void
    {
        $this->data .= $data;
    }

    final protected function getBufferedData(): string
    {
        return $this->data;
    }
}
