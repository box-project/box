<?php

namespace KevinGH\Box\Annotation;

use KevinGH\Box\Annotation\Convert\AbstractConvert;
use KevinGH\Box\Annotation\Tokens;

/**
 * A simple test converter that increments a counter.
 *
 * @author Kevin Herrera <kevin@herrera.io>
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
    protected function handle()
    {
        $this->result++;
    }

    /**
     * @override
     */
    protected function reset(Tokens $tokens)
    {
        $this->result = 0;
        $this->tokens = $tokens;
    }
}
