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

use ArrayAccess;
use Countable;
use Doctrine\Common\Annotations\DocLexer;
use Iterator;
use KevinGH\Box\Annotation\Exception\Exception;
use KevinGH\Box\Annotation\Exception\InvalidTokenException;
use KevinGH\Box\Annotation\Exception\LogicException;
use KevinGH\Box\Annotation\Exception\OutOfRangeException;

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
    private static $hasValue = [
        DocLexer::T_FALSE => true,
        DocLexer::T_FLOAT => true,
        DocLexer::T_IDENTIFIER => true,
        DocLexer::T_INTEGER => true,
        DocLexer::T_NULL => true,
        DocLexer::T_STRING => true,
        DocLexer::T_TRUE => true,
    ];

    /**
     * The current offset.
     *
     * @var int
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
    private static $valid = [
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
    ];

    /**
     * Sets the list of tokens to manage.
     *
     * @param array $tokens the list of tokens
     */
    public function __construct(array $tokens)
    {
        $this->offset = 0;
        $this->tokens = array_values($tokens);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->tokens);
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return $this->getToken($this->offset);
    }

    /**
     * Returns the array copy of the list of tokens.
     *
     * @return array the list of tokens
     */
    public function getArray(): array
    {
        return $this->tokens;
    }

    /**
     * Returns the token identifier at the offset.
     *
     * @param int $offset the offset to retrieve
     *
     * @return int the token identifier
     */
    public function getId($offset = null): int
    {
        if (null !== ($token = $this->getToken($offset))) {
            return $token[0];
        }

        return null;
    }

    /**
     * Returns the key for the value at the offset.
     *
     * @param int $offset the value's offset
     *
     * @return int|string the key
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
     * @param int   $offset  the offset to retrieve
     * @param array $default the default token to return
     *
     * @throws Exception
     * @throws InvalidTokenException if the token is not valid
     *
     * @return array the token, or the default
     */
    public function getToken($offset = null, array $default = null): array
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
     * @param int $offset the token offset
     *
     * @throws Exception
     * @throws LogicException if the token is not expected to have a value
     *
     * @return mixed the processed value
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
     * {@inheritdoc}
     */
    public function key()
    {
        return $this->offset;
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        ++$this->offset;

        if (isset($this->tokens[$this->offset])) {
            return $this->tokens[$this->offset];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return isset($this->tokens[$offset]);
    }

    /**
     * {@inheritdoc}
     *
     * @throws OutOfRangeException if the offset is invalid
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
     * {@inheritdoc}
     *
     * @throws LogicException if called
     */
    public function offsetSet($offset, $value): void
    {
        throw LogicException::create(
            'New values cannot be added to the list of tokens.'
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws LogicException if called
     */
    public function offsetUnset($offset): void
    {
        throw Logicexception::create(
            'Existing tokens cannot be removed from the list of tokens.'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        $this->offset = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return isset($this->tokens[$this->offset]);
    }
}
