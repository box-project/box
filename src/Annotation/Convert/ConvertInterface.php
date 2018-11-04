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

namespace KevinGH\Box\Annotation\Convert;

use KevinGH\Box\Annotation\Tokens;

/**
 * Defines how a converter class must be implemented.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
interface ConvertInterface
{
    /**
     * Converts the list of tokens and returns the result.
     *
     * @param Tokens $tokens the list of tokens
     *
     * @return mixed the result
     */
    public function convert(Tokens $tokens);
}
