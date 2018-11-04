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

use Doctrine\Common\Annotations\DocLexer;
use KevinGH\Box\Annotation\Exception\Exception;
use KevinGH\Box\Annotation\Exception\SyntaxException;

/**
 * Parses annotation tokens from a docblock.
 *
 * This class will use a lexer to parse out a series of tokens from a given
 * docblock. Each token in the series represents a portion of an annotation
 * that was parsed. These tokens can be used to generate alternative
 * representations, such as native values.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Tokenizer
{
    /**
     * The namespace aliases.
     *
     * @var array
     */
    private $aliases = [];

    /**
     * The list of valid class identifier.
     *
     * @var array
     */
    private static $classIdentifiers = [
        DocLexer::T_IDENTIFIER,
        DocLexer::T_TRUE,
        DocLexer::T_FALSE,
        DocLexer::T_NULL,
    ];

    /**
     * The list of ignored annotation identifiers.
     *
     * @var array
     */
    private $ignored = [];

    /**
     * The annotations lexer.
     *
     * @var DocLexer
     */
    private $lexer;

    /**
     * Initializes the lexer.
     */
    public function __construct()
    {
        $this->lexer = new DocLexer();
    }

    /**
     * Parses the docblock and returns its annotation tokens.
     *
     * @param string $input   the docblock
     * @param array  $aliases the namespace aliases
     *
     * @return array the list of tokens
     */
    public function parse($input, array $aliases = []): array
    {
        if (0 !== strpos(ltrim($input), '/**')) {
            return [];
        }

        if (false == ($position = strpos($input, '@'))) {
            return [];
        }

        if (0 < $position) {
            --$position;
        }

        $input = substr($input, $position);
        $input = trim($input, '*/ ');

        $this->aliases = $aliases;

        $this->lexer->setInput($input);
        $this->lexer->moveNext();

        return $this->getAnnotations();
    }

    /**
     * Sets the annotation identifiers to ignore.
     *
     * @param array $ignore the list of ignored identifiers
     */
    public function ignore(array $ignore): void
    {
        $this->ignored = $ignore;
    }

    /**
     * Returns the tokens for the next annotation.
     *
     * @return array the tokens
     */
    private function getAnnotation(): array
    {
        $this->match(DocLexer::T_AT);

        // get the complete name
        $identifier = $this->getIdentifier();

        // skip if necessary
        if (in_array($identifier, $this->ignored, true)) {
            return null;
        }

        // use alias if applicable
        if (false !== ($pos = strpos($identifier, '\\'))) {
            $alias = substr($identifier, 0, $pos);

            if (isset($this->aliases[$alias])) {
                $identifier = $this->aliases[$alias]
                            .'\\'
                            .substr($identifier, $pos + 1);
            }
        } elseif (isset($this->aliases[$identifier])) {
            $identifier = $this->aliases[$identifier];
        }

        // return the @, name, and any values found
        return array_merge(
            [
                [DocLexer::T_AT],
                [DocLexer::T_IDENTIFIER, $identifier],
            ],
            $this->getValues()
        );
    }

    /**
     * Returns the tokens for all available annotations.
     *
     * @return array the tokens
     */
    private function getAnnotations(): array
    {
        $tokens = [];

        while (null !== $this->lexer->lookahead) {
            // start at the @ symbol
            if (DocLexer::T_AT !== $this->lexer->lookahead['type']) {
                $this->lexer->moveNext();

                continue;
            }

            // something about being preceded by a non-catchable pattern
            $position = $this->lexer->token['position']
                + strlen($this->lexer->token['value']);

            if ((null !== $this->lexer->token)
                && ($this->lexer->lookahead['position'] === $position)) {
                $this->lexer->moveNext();

                continue;
            }

            // make sure we get a valid annotation name
            if ((null === ($glimpse = $this->lexer->glimpse()))
                || ((DocLexer::T_NAMESPACE_SEPARATOR !== $glimpse['type'])
                    && !in_array($glimpse['type'], self::$classIdentifiers, true))) {
                $this->lexer->moveNext();

                continue;
            }

            // find them all and merge them to the list
            if (null !== ($token = $this->getAnnotation())) {
                $tokens = array_merge($tokens, $token);
            }
        }

        return $tokens;
    }

    /**
     * Returns the tokens for the next array of values.
     *
     * @return array the tokens
     */
    private function getArray(): array
    {
        $this->match(DocLexer::T_OPEN_CURLY_BRACES);

        $tokens = [
            [DocLexer::T_OPEN_CURLY_BRACES],
        ];

        // check if empty array, bail early if it is
        if ($this->lexer->isNextToken(DocLexer::T_CLOSE_CURLY_BRACES)) {
            $this->match(DocLexer::T_CLOSE_CURLY_BRACES);

            $tokens[] = [DocLexer::T_CLOSE_CURLY_BRACES];

            return $tokens;
        }

        // collect the first value
        $tokens = array_merge($tokens, $this->getArrayEntry());

        // collect the remaining values
        while ($this->lexer->isNextToken(DocLexer::T_COMMA)) {
            $this->match(DocLexer::T_COMMA);

            $tokens[] = [DocLexer::T_COMMA];

            if ($this->lexer->isNextToken(DocLexer::T_CLOSE_CURLY_BRACES)) {
                break;
            }

            $tokens = array_merge($tokens, $this->getArrayEntry());
        }

        // end the collection
        $this->match(DocLexer::T_CLOSE_CURLY_BRACES);

        $tokens[] = [DocLexer::T_CLOSE_CURLY_BRACES];

        return $tokens;
    }

    /**
     * Returns the tokens for the next array entry.
     *
     * @return array the tokens
     */
    private function getArrayEntry(): array
    {
        $glimpse = $this->lexer->glimpse();
        $tokens = [];

        // append the correct assignment token: ":" or "="
        if (DocLexer::T_COLON === $glimpse['type']) {
            $token = [DocLexer::T_COLON];
        } elseif (DocLexer::T_EQUALS === $glimpse['type']) {
            $token = [DocLexer::T_EQUALS];
        }

        // is it an assignment?
        if (isset($token)) {
            // if the key is a constant, hand off
            if ($this->lexer->isNextToken(DocLexer::T_IDENTIFIER)) {
                $tokens = $this->getConstant();

            // match only integer and string keys
            } else {
                $this->matchAny(
                    [
                        DocLexer::T_INTEGER,
                        DocLexer::T_STRING,
                    ]
                );

                $tokens = [
                    [
                        $this->lexer->token['type'],
                        $this->lexer->token['value'],
                    ],
                ];
            }

            $tokens[] = $token;

            $this->matchAny(
                [
                    DocLexer::T_COLON,
                    DocLexer::T_EQUALS,
                ]
            );
        }

        // merge in the value
        return array_merge($tokens, $this->getPlainValue());
    }

    /**
     * Returns the tokens for the next assigned (key/value) value.
     *
     * @return array the tokens
     */
    private function getAssignedValue(): array
    {
        $this->match(DocLexer::T_IDENTIFIER);

        $tokens = [
            [DocLexer::T_IDENTIFIER, $this->lexer->token['value']],
            [DocLexer::T_EQUALS],
        ];

        $this->match(DocLexer::T_EQUALS);

        return array_merge($tokens, $this->getPlainValue());
    }

    /**
     * Returns the current constant value for the current annotation.
     *
     * @return array the tokens
     */
    private function getConstant(): array
    {
        $identifier = $this->getIdentifier();
        $tokens = [];

        // check for a special constant type
        switch (strtolower($identifier)) {
            case 'true':
                $tokens[] = [DocLexer::T_TRUE, $identifier];
                break;
            case 'false':
                $tokens[] = [DocLexer::T_FALSE, $identifier];
                break;
            case 'null':
                $tokens[] = [DocLexer::T_NULL, $identifier];
                break;
            default:
                $tokens[] = [DocLexer::T_IDENTIFIER, $identifier];
        }

        return $tokens;
    }

    /**
     * Returns the next identifier.
     *
     * @throws Exception
     * @throws SyntaxException if a syntax error is found
     *
     * @return string the identifier
     */
    private function getIdentifier(): string
    {
        // grab the first bit of the identifier
        if ($this->lexer->isNextTokenAny(self::$classIdentifiers)) {
            $this->lexer->moveNext();

            $name = $this->lexer->token['value'];
        } else {
            throw SyntaxException::expectedToken(
                'namespace separator or identifier',
                null,
                $this->lexer
            );
        }

        // grab the remaining bits
        $position = $this->lexer->token['position']
                  + strlen($this->lexer->token['value']);

        while (($this->lexer->lookahead['position'] === $position)
            && $this->lexer->isNextToken(DocLexer::T_NAMESPACE_SEPARATOR)) {
            $this->match(DocLexer::T_NAMESPACE_SEPARATOR);
            $this->matchAny(self::$classIdentifiers);

            $name .= '\\'.$this->lexer->token['value'];
        }

        return $name;
    }

    /**
     * Returns the tokens for the next "plain" value.
     *
     * @throws Exception
     * @throws SyntaxException if a syntax error is found
     *
     * @return array the tokens
     */
    private function getPlainValue(): array
    {
        // check if array, then hand off
        if ($this->lexer->isNextToken(DocLexer::T_OPEN_CURLY_BRACES)) {
            return $this->getArray();
        }

        // check if nested annotation, then hand off
        if ($this->lexer->isNextToken(DocLexer::T_AT)) {
            return $this->getAnnotation();
        }

        // check if constant, then hand off
        if ($this->lexer->isNextToken(DocLexer::T_IDENTIFIER)) {
            return $this->getConstant();
        }

        $tokens = [];

        // determine type, or throw syntax error if unrecognized
        switch ($this->lexer->lookahead['type']) {
            case DocLexer::T_FALSE:
            case DocLexer::T_FLOAT:
            case DocLexer::T_INTEGER:
            case DocLexer::T_NULL:
            case DocLexer::T_STRING:
            case DocLexer::T_TRUE:
                $this->match($this->lexer->lookahead['type']);

                $tokens[] = [
                    $this->lexer->token['type'],
                    $this->lexer->token['value'],
                ];

                break;
            default:
                throw SyntaxException::expectedToken(
                    'PlainValue',
                    null,
                    $this->lexer
                );
        }

        return $tokens;
    }

    /**
     * Returns the tokens for the next value.
     *
     * @return array the tokens
     */
    private function getValue(): array
    {
        $glimpse = $this->lexer->glimpse();

        // check if it's an assigned value: @example(assigned="value")
        if (DocLexer::T_EQUALS === $glimpse['type']) {
            return $this->getAssignedValue();
        }

        return $this->getPlainValue();
    }

    /**
     * Returns the tokens for all of the values for the current annotation.
     *
     * @throws Exception
     * @throws SyntaxException if a syntax error is found
     *
     * @return array the tokens
     */
    private function getValues(): array
    {
        $tokens = [];

        // check if a value list is given
        if ($this->lexer->isNextToken(DocLexer::T_OPEN_PARENTHESIS)) {
            $this->match(DocLexer::T_OPEN_PARENTHESIS);

            $tokens[] = [DocLexer::T_OPEN_PARENTHESIS];

            // skip if we are given an empty list: @example()
            if ($this->lexer->isNextToken(DocLexer::T_CLOSE_PARENTHESIS)) {
                $this->match(DocLexer::T_CLOSE_PARENTHESIS);

                $tokens[] = [DocLexer::T_CLOSE_PARENTHESIS];

                return $tokens;
            }

            // skip if no list is given
        } else {
            return $tokens;
        }

        // collect the first value
        $tokens = array_merge($tokens, $this->getValue());

        // check for comma separated values and collect those too
        while ($this->lexer->isNextToken(DocLexer::T_COMMA)) {
            $this->match(DocLexer::T_COMMA);

            $tokens[] = [DocLexer::T_COMMA];

            $token = $this->lexer->lookahead;
            $value = $this->getValue();

            // no multiple trailing commas
            if (empty($value)) {
                throw SyntaxException::expectedToken('Value', $token);
            }

            $tokens = array_merge($tokens, $value);
        }

        // end the list
        $this->match(DocLexer::T_CLOSE_PARENTHESIS);

        $tokens[] = [DocLexer::T_CLOSE_PARENTHESIS];

        return $tokens;
    }

    /**
     * Matches the next token and advances.
     *
     * @param int $token the next token to match
     *
     * @throws Exception
     * @throws SyntaxException if a syntax error is found
     *
     * @return null|array TRUE if the next token matches, FALSE if not
     */
    private function match($token): ?array
    {
        if (!$this->lexer->isNextToken($token)) {
            throw SyntaxException::expectedToken(
                $this->lexer->getLiteral($token),
                null,
                $this->lexer
            );
        }

        return $this->lexer->moveNext();
    }

    /**
     * Matches any one of the tokens and advances.
     *
     * @param array $tokens the list of tokens
     *
     * @throws Exception
     * @throws SyntaxException if a syntax error is found
     *
     * @return bool TRUE if the next token matches, FALSE if not
     */
    private function matchAny(array $tokens): bool
    {
        if (!$this->lexer->isNextTokenAny($tokens)) {
            throw SyntaxException::expectedToken(
                implode(
                    ' or ',
                    array_map([$this->lexer, 'getLiteral'], $tokens)
                ),
                null,
                $this->lexer
            );
        }

        return $this->lexer->moveNext();
    }
}
