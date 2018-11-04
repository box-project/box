<?php

namespace KevinGH\Box\Annotation;

use Doctrine\Common\Annotations\DocLexer;
use KevinGH\Box\Annotation\Exception\Exception;
use KevinGH\Box\Annotation\Exception\InvalidArgumentException;
use KevinGH\Box\Annotation\Exception\UnexpectedTokenException;

/**
 * An extension of Tokens that performs sequence validation.
 *
 * The Tokens class will only validate individual tokens. This class extends
 * that functionality, and also validates the order in which the tokens are
 * used. This can be considered the equivalent of performing a syntax check
 * on a docblock, but after it has been parsed.
 *
 * This class is probably only really useful for debugging, or if you are
 * using tokens that did not come directly from the tokenizer. If you are
 * using tokens directly from the tokenizer, there is no need to use this
 * class over the Tokens class.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Sequence extends Tokens
{
    /**
     * The list of valid sequences for a token.
     *
     * @var array
     */
    private static $sequences = array(
        DocLexer::T_AT => array(
            DocLexer::T_CLOSE_PARENTHESIS => true,
            DocLexer::T_COLON => true,
            DocLexer::T_COMMA => true,
            DocLexer::T_EQUALS => true,
            DocLexer::T_IDENTIFIER => DocLexer::T_AT,
            DocLexer::T_OPEN_CURLY_BRACES => true,
            DocLexer::T_OPEN_PARENTHESIS => true,
        ),
        DocLexer::T_CLOSE_CURLY_BRACES => array(
            DocLexer::T_CLOSE_CURLY_BRACES => true,
            DocLexer::T_CLOSE_PARENTHESIS => true,
            DocLexer::T_COMMA => true,
            DocLexer::T_FALSE => true,
            DocLexer::T_FLOAT => true,
            DocLexer::T_IDENTIFIER => true,
            DocLexer::T_INTEGER => true,
            DocLexer::T_NULL => true,
            DocLexer::T_OPEN_CURLY_BRACES => true,
            DocLexer::T_STRING => true,
            DocLexer::T_TRUE => true,
        ),
        DocLexer::T_CLOSE_PARENTHESIS => array(
            DocLexer::T_CLOSE_CURLY_BRACES => true,
            DocLexer::T_CLOSE_PARENTHESIS => true,
            DocLexer::T_COMMA => true,
            DocLexer::T_FALSE => true,
            DocLexer::T_FLOAT => true,
            DocLexer::T_IDENTIFIER => true,
            DocLexer::T_INTEGER => true,
            DocLexer::T_NULL => true,
            DocLexer::T_OPEN_PARENTHESIS => true,
            DocLexer::T_STRING => true,
            DocLexer::T_TRUE => true,
        ),
        DocLexer::T_COLON => array(
            DocLexer::T_IDENTIFIER => true,
            DocLexer::T_INTEGER => true,
            DocLexer::T_STRING => true,
        ),
        DocLexer::T_COMMA => array(
            DocLexer::T_CLOSE_CURLY_BRACES => true,
            DocLexer::T_CLOSE_PARENTHESIS => true,
            DocLexer::T_FALSE => true,
            DocLexer::T_FLOAT => true,
            DocLexer::T_IDENTIFIER => true,
            DocLexer::T_INTEGER => true,
            DocLexer::T_NULL => true,
            DocLexer::T_STRING => true,
            DocLexer::T_TRUE => true,
        ),
        DocLexer::T_EQUALS => array(
            DocLexer::T_IDENTIFIER => true,
            DocLexer::T_INTEGER => true,
            DocLexer::T_STRING => true,
        ),
        DocLexer::T_FALSE => array(
            DocLexer::T_COLON => true,
            DocLexer::T_COMMA => true,
            DocLexer::T_EQUALS => true,
            DocLexer::T_OPEN_CURLY_BRACES => true,
            DocLexer::T_OPEN_PARENTHESIS => true,
        ),
        DocLexer::T_FLOAT => array(
            DocLexer::T_COLON => true,
            DocLexer::T_COMMA => true,
            DocLexer::T_EQUALS => true,
            DocLexer::T_OPEN_CURLY_BRACES => true,
            DocLexer::T_OPEN_PARENTHESIS => true,
        ),
        DocLexer::T_IDENTIFIER => array(
            DocLexer::T_AT => true,
            DocLexer::T_COLON => true,
            DocLexer::T_COMMA => true,
            DocLexer::T_EQUALS => true,
            DocLexer::T_OPEN_CURLY_BRACES => true,
            DocLexer::T_OPEN_PARENTHESIS => true,
        ),
        DocLexer::T_INTEGER => array(
            DocLexer::T_COLON => true,
            DocLexer::T_COMMA => true,
            DocLexer::T_EQUALS => true,
            DocLexer::T_OPEN_CURLY_BRACES => true,
            DocLexer::T_OPEN_PARENTHESIS => true,
        ),
        DocLexer::T_NAMESPACE_SEPARATOR => false,
        DocLexer::T_NONE => false,
        DocLexer::T_NULL => array(
            DocLexer::T_COLON => true,
            DocLexer::T_COMMA => true,
            DocLexer::T_EQUALS => true,
            DocLexer::T_OPEN_CURLY_BRACES => true,
            DocLexer::T_OPEN_PARENTHESIS => true,
        ),
        DocLexer::T_OPEN_CURLY_BRACES => array(
            DocLexer::T_COLON => true,
            DocLexer::T_COMMA => true,
            DocLexer::T_EQUALS => true,
            DocLexer::T_OPEN_CURLY_BRACES => true,
            DocLexer::T_OPEN_PARENTHESIS => true,
        ),
        DocLexer::T_OPEN_PARENTHESIS => array(
            DocLexer::T_IDENTIFIER => DocLexer::T_AT,
        ),
        DocLexer::T_STRING => array(
            DocLexer::T_COLON => true,
            DocLexer::T_COMMA => true,
            DocLexer::T_EQUALS => true,
            DocLexer::T_OPEN_CURLY_BRACES => true,
            DocLexer::T_OPEN_PARENTHESIS => true,
        ),
        DocLexer::T_TRUE => array(
            DocLexer::T_COLON => true,
            DocLexer::T_COMMA => true,
            DocLexer::T_EQUALS => true,
            DocLexer::T_OPEN_CURLY_BRACES => true,
            DocLexer::T_OPEN_PARENTHESIS => true,
        ),
    );

    /**
     * {@inheritDoc}
     *
     * @param array|Tokens $tokens The list of tokens.
     *
     * @throws InvalidArgumentException If the $tokens argument is not either
     *                                  an array or instance of Tokens.
     */
    public function __construct($tokens)
    {
        if ($tokens instanceof Tokens) {
            $tokens = $tokens->getArray();
        } elseif (!is_array($tokens)) {
            throw InvalidArgumentException::create(
                'The $tokens argument must be an array or instance of Tokens.'
            );
        }

        parent::__construct($tokens);
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     * @throws UnexpectedTokenException If the token is not expected.
     */
    public function current()
    {
        $token = parent::current();
        $offset = $this->key();
        $before = isset($this[$offset - 1])
                ? $this[$offset - 1]
                : null;

        // is it an unused token?
        if (false === self::$sequences[$token[0]]) {
            throw UnexpectedTokenException::create(
                'Token #%d (%d) is not used by this library.',
                $offset,
                $token[0]
            );

            // not in the list of expected tokens?
        } elseif ((empty($before) && (DocLexer::T_AT !== $token[0]))
            || ($before && !isset(self::$sequences[$token[0]][$before[0]]))) {
            throw UnexpectedTokenException::create(
                'Token #%d (%d) is not expected here.',
                $offset,
                $token[0]
            );

            // before token has another before requirement?
        } elseif (isset(self::$sequences[$token[0]][$before[0]])
            && (true !== self::$sequences[$token[0]][$before[0]])) {
            $ancestor = isset($this[$offset - 2])
                      ? $this[$offset - 2]
                      : null;

            if (!$ancestor
                || ($ancestor[0] !== self::$sequences[$token[0]][$before[0]])) {
                throw UnexpectedTokenException::create(
                    'Token #%d (%d) is not expected here.',
                    $offset,
                    $token[0]
                );
            }
        }

        return $token;
    }
}
