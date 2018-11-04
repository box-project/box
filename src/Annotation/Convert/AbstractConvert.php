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
 * Manages the basic tasks for converters.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
abstract class AbstractConvert implements ConvertInterface
{
    /**
     * The conversion result.
     *
     * @var mixed
     */
    protected $result;

    /**
     * The list of tokens.
     *
     * @var Tokens
     */
    protected $tokens;

    /**
     * {@inheritdoc}
     */
    public function convert(Tokens $tokens)
    {
        $this->reset($tokens);

        while ($tokens->valid()) {
            $this->handle();
            $tokens->next();
        }

        return $this->result;
    }

    /**
     * Handles the conversion of the current token.
     */
    abstract protected function handle(): void;

    /**
     * Resets the state of the converter.
     *
     * @param Tokens $tokens the new list of tokens
     */
    abstract protected function reset(Tokens $tokens): void;
}
