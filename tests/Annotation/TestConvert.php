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

namespace KevinGH\Box\Annotation;

use KevinGH\Box\Annotation\Convert\AbstractConvert;

/**
 * A simple test converter that increments a counter.
 */
class TestConvert extends AbstractConvert
{
    /**
     * @override
     */
    public $tokens;

    /**
     * Set the counter to 100.
     */
    public function __construct()
    {
        $this->result = 100;
    }

    /**
     * @override
     */
    protected function handle(): void
    {
        ++$this->result;
    }

    /**
     * @override
     */
    protected function reset(Tokens $tokens): void
    {
        $this->result = 0;
        $this->tokens = $tokens;
    }
}
