<?php

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
     * {@inheritDoc}
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
    abstract protected function handle();

    /**
     * Resets the state of the converter.
     *
     * @param Tokens $tokens The new list of tokens.
     */
    abstract protected function reset(Tokens $tokens);
}
