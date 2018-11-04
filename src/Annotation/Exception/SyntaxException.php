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

namespace KevinGH\Box\Annotation\Exception;

use Doctrine\Common\Annotations\DocLexer;

/**
 * This exception is thrown if the annotation syntax is not valid.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class SyntaxException extends Exception
{
    /**
     * Creates a new exception for the expected token.
     *
     * @param string   $expected the expected token
     * @param array    $token    the actual token
     * @param DocLexer $lexer    the lexer
     *
     * @return SyntaxException the new exception
     */
    public static function expectedToken(
        $expected,
        array $token = null,
        DocLexer $lexer = null
    ): self {
        if ((null === $token) && $lexer) {
            $token = $lexer->lookahead;
        }

        $message = "Expected $expected, received ";

        if ($token) {
            $message .= "'{$token['value']}' at position {$token['position']}.";
        } else {
            $message .= 'end of string.';
        }

        return new self($message);
    }
}
