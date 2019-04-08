<?php

declare(strict_types=1);

namespace KevinGH\Box;

/**
 * @private
 */
trait NotInstantiable
{
    private function __construct()
    {
    }
}