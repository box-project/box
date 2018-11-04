<?php

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
     * @param string   $expected The expected token.
     * @param array    $token    The actual token.
     * @param DocLexer $lexer    The lexer.
     *
     * @return SyntaxException The new exception.
     */
    public static function expectedToken(
        $expected,
        array $token = null,
        DocLexer $lexer = null
    ) {
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
