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

/**
 * Buffers the hash as opposed to updating incrementally.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
abstract class AbstractBufferedHash implements VerifyInterface
{
    /**
     * The buffered data.
     *
     * @var string
     */
    private $data;

    /**
     * @see VerifyInterface::update
     *
     * @param mixed $data
     */
    public function update($data): void
    {
        $this->data .= $data;
    }

    /**
     * Returns the buffered data.
     *
     * @return string the data
     */
    protected function getData()
    {
        return $this->data;
    }
}
