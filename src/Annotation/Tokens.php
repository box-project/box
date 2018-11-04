<?php

namespace KevinGH\Box\Annotation;

use ArrayAccess;
use Countable;
use Doctrine\Common\Annotations\DocLexer;
use KevinGH\Box\Annotation\Exception\Exception;
use KevinGH\Box\Annotation\Exception\InvalidTokenException;
use KevinGH\Box\Annotation\Exception\LogicException;
use KevinGH\Box\Annotation\Exception\OutOfRangeException;
use Iterator;

/**
 * Manages and validates an immutable list of tokens.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Tokens implements ArrayAccess, Countable, Iterator
{
    /**
     * The list of tokens that are expected to have a value.
     *
     * @var array
     */
    private static $hasValue = array(
        DocLexer::T_FALSE => true,
        DocLexer::T_FLOAT => true,
        DocLexer::T_IDENTIFIER => true,
        DocLexer::T_INTEGER => true,
        DocLexer::T_NULL => true,
        DocLexer::T_STRING => true,
        DocLexer::T_TRUE => true,
    );

    /**
     * The current offset.
     *
     * @var integer
     */
    private $offset;

    /**
     * The list of tokens.
     *
     * @var array
     */
    private $tokens;

    /**
     * The list of valid tokens.
     *
     * @var array
     */
    private static $valid = array(
        DocLexer::T_AT => true,
        DocLexer::T_CLOSE_CURLY_BRACES => true,
        DocLexer::T_CLOSE_PARENTHESIS => true,
        DocLexer::T_COLON => true,
        DocLexer::T_COMMA => true,
        DocLexer::T_EQUALS => true,
        DocLexer::T_FALSE => true,
        DocLexer::T_FLOAT => true,
        DocLexer::T_IDENTIFIER => true,
        DocLexer::T_INTEGER => true,
        DocLexer::T_NAMESPACE_SEPARATOR => true,
        DocLexer::T_NONE => true,
        DocLexer::T_NULL => true,
        DocLexer::T_OPEN_CURLY_BRACES => true,
        DocLexer::T_OPEN_PARENTHESIS => true,
        DocLexer::T_STRING => true,
        DocLexer::T_TRUE => true,
    );

    /**
     * Sets the list of tokens to manage.
     *
     * @param array $tokens The list of tokens.
     */
    public function __construct(array $tokens)
    {
        $this->offset = 0;
        $this->tokens = array_values($tokens);
    }

    /**
     * {@inheritDoc}
     */
    public function count()
    {
        return count($this->tokens);
    }

    /**
     * {@inheritDoc}
     */
    public function current()
    {
        return $this->getToken($this->offset);
    }

    /**
     * Returns the array copy of the list of tokens.
     *
     * @return array The list of tokens.
     */
    public function getArray()
    {
        return $this->tokens;
    }

    /**
     * Returns the token identifier at the offset.
     *
     * @param integer $offset The offset to retrieve.
     *
     * @return integer The token identifier.
     */
    public function getId($offset = null)
    {
        if (null !== ($token = $this->getToken($offset))) {
            return $token[0];
        }

        return null;
    }

    /**
     * Returns the key for the value at the offset.
     *
     * @param integer $offset The value's offset.
     *
     * @return integer|string The key.
     */
    public function getKey($offset = null)
    {
        if (null === $offset) {
            $offset = $this->offset;
        }

        if ((null !== ($op = $this->getId(--$offset)))
            && ((DocLexer::T_COLON === $op)
                || (DocLexer::T_EQUALS === $op))) {
            return $this->getValue(--$offset);
        }

        return null;
    }

    /**
     * Returns the token at the offset, or the default given.
     *
     * @param integer $offset  The offset to retrieve.
     * @param array   $default The default token to return.
     *
     * @return array The token, or the default.
     *
     * @throws Exception
     * @throws InvalidTokenException If the token is not valid.
     */
    public function getToken($offset = null, array $default = null)
    {
        if (null === $offset) {
            $offset = $this->offset;
        }

        if (isset($this->tokens[$offset])) {
            if (!isset($this->tokens[$offset][0])) {
                throw InvalidTokenException::create(
                    'Token #%d is missing its token identifier.',
                    $offset
                );
            }

            if (!isset(self::$valid[$this->tokens[$offset][0]])) {
                throw InvalidTokenException::create(
                    'Token #%d does not have a valid token identifier.',
                    $offset
                );
            }

            if ((isset(self::$hasValue[$this->tokens[$offset][0]]))
                && !isset($this->tokens[$offset][1])) {
                throw InvalidTokenException::create(
                    'Token #%d (%d) is missing its value.',
                    $offset,
                    $this->tokens[$offset][0]
                );
            }

            return $this->tokens[$offset];
        }

        return $default;
    }

    /**
     * Returns the processed value of the specified token.
     *
     * @param integer $offset The token offset.
     *
     * @return mixed The processed value.
     *
     * @throws Exception
     * @throws LogicException If the token is not expected to have a value.
     */
    public function getValue($offset = null)
    {
        if (null === $offset) {
            $offset = $this->offset;
        }

        $token = $this->getToken($offset);

        if (!isset(self::$hasValue[$token[0]])) {
            throw LogicException::create(
                'Token #%d (%d) is not expected to have a value.',
                $offset,
                $token[0]
            );
        }

        switch ($token[0]) {
            case DocLexer::T_FALSE:
                return false;
            case DocLexer::T_FLOAT:
                return (float) $token[1];
            case DocLexer::T_INTEGER:
                return (int) $token[1];
            case DocLexer::T_IDENTIFIER:
            case DocLexer::T_STRING:
                return $token[1];
            case DocLexer::T_TRUE:
                return true;
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function key()
    {
        return $this->offset;
    }

    /**
     * {@inheritDoc}
     */
    public function next()
    {
        $this->offset++;

        if (isset($this->tokens[$this->offset])) {
            return $this->tokens[$this->offset];
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function offsetExists($offset)
    {
        return isset($this->tokens[$offset]);
    }

    /**
     * {@inheritDoc}
     *
     * @throws OutOfRangeException If the offset is invalid.
     */
    public function offsetGet($offset)
    {
        if (null === ($token = $this->getToken($offset))) {
            throw OutOfRangeException::create(
                'No value is set at offset %d.',
                $offset
            );
        }

        return $token;
    }

    /**
     * {@inheritDoc}
     *
     * @throws LogicException If called.
     */
    public function offsetSet($offset, $value)
    {
        throw LogicException::create(
            'New values cannot be added to the list of tokens.'
        );
    }

    /**
     * {@inheritDoc}
     *
     * @throws LogicException If called.
     */
    public function offsetUnset($offset)
    {
        throw Logicexception::create(
            'Existing tokens cannot be removed from the list of tokens.'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function rewind()
    {
        $this->offset = 0;
    }

    /**
     * {@inheritDoc}
     */
    public function valid()
    {
        return isset($this->tokens[$this->offset]);
    }
}
