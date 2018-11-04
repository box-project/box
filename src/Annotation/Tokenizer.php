<?php

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
    private $aliases = array();

    /**
     * The list of valid class identifier.
     *
     * @var array
     */
    private static $classIdentifiers = array(
        DocLexer::T_IDENTIFIER,
        DocLexer::T_TRUE,
        DocLexer::T_FALSE,
        DocLexer::T_NULL
    );

    /**
     * The list of ignored annotation identifiers.
     *
     * @var array
     */
    private $ignored = array();

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
     * @param string $input   The docblock.
     * @param array  $aliases The namespace aliases.
     *
     * @return array The list of tokens.
     */
    public function parse($input, array $aliases = array())
    {
        if (0 !== strpos(ltrim($input), '/**')) {
            return array();
        }

        if (false == ($position = strpos($input, '@'))) {
            return array();
        }

        if (0 < $position) {
            $position -= 1;
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
     * @param array $ignore The list of ignored identifiers.
     */
    public function ignore(array $ignore)
    {
        $this->ignored = $ignore;
    }

    /**
     * Returns the tokens for the next annotation.
     *
     * @return array The tokens.
     */
    private function getAnnotation()
    {
        $this->match(DocLexer::T_AT);

        // get the complete name
        $identifier = $this->getIdentifier();

        // skip if necessary
        if (in_array($identifier, $this->ignored)) {
            return null;
        }

        // use alias if applicable
        if (false !== ($pos = strpos($identifier, '\\'))) {
            $alias = substr($identifier, 0, $pos);

            if (isset($this->aliases[$alias])) {
                $identifier = $this->aliases[$alias]
                            . '\\'
                            . substr($identifier, $pos + 1);
            }
        } elseif (isset($this->aliases[$identifier])) {
            $identifier = $this->aliases[$identifier];
        }

        // return the @, name, and any values found
        return array_merge(
            array(
                array(DocLexer::T_AT),
                array(DocLexer::T_IDENTIFIER, $identifier)
            ),
            $this->getValues()
        );
    }

    /**
     * Returns the tokens for all available annotations.
     *
     * @return array The tokens.
     */
    private function getAnnotations()
    {
        $tokens = array();

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
                    && !in_array($glimpse['type'], self::$classIdentifiers))) {
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
     * @return array The tokens.
     */
    private function getArray()
    {
        $this->match(DocLexer::T_OPEN_CURLY_BRACES);

        $tokens = array(
            array(DocLexer::T_OPEN_CURLY_BRACES)
        );

        // check if empty array, bail early if it is
        if ($this->lexer->isNextToken(DocLexer::T_CLOSE_CURLY_BRACES)) {
            $this->match(DocLexer::T_CLOSE_CURLY_BRACES);

            $tokens[] = array(DocLexer::T_CLOSE_CURLY_BRACES);

            return $tokens;
        }

        // collect the first value
        $tokens = array_merge($tokens, $this->getArrayEntry());

        // collect the remaining values
        while ($this->lexer->isNextToken(DocLexer::T_COMMA)) {
            $this->match(DocLexer::T_COMMA);

            $tokens[] = array(DocLexer::T_COMMA);

            if ($this->lexer->isNextToken(DocLexer::T_CLOSE_CURLY_BRACES)) {
                break;
            }

            $tokens = array_merge($tokens, $this->getArrayEntry());
        }

        // end the collection
        $this->match(DocLexer::T_CLOSE_CURLY_BRACES);

        $tokens[] = array(DocLexer::T_CLOSE_CURLY_BRACES);

        return $tokens;
    }

    /**
     * Returns the tokens for the next array entry.
     *
     * @return array The tokens.
     */
    private function getArrayEntry()
    {
        $glimpse = $this->lexer->glimpse();
        $tokens = array();

        // append the correct assignment token: ":" or "="
        if (DocLexer::T_COLON === $glimpse['type']) {
            $token = array(DocLexer::T_COLON);
        } elseif (DocLexer::T_EQUALS === $glimpse['type']) {
            $token = array(DocLexer::T_EQUALS);
        }

        // is it an assignment?
        if (isset($token)) {

            // if the key is a constant, hand off
            if ($this->lexer->isNextToken(DocLexer::T_IDENTIFIER)) {
                $tokens = $this->getConstant();

                // match only integer and string keys
            } else {
                $this->matchAny(
                    array(
                        DocLexer::T_INTEGER,
                        DocLexer::T_STRING
                    )
                );

                $tokens = array(
                    array(
                        $this->lexer->token['type'],
                        $this->lexer->token['value']
                    )
                );
            }

            $tokens[] = $token;

            $this->matchAny(
                array(
                    DocLexer::T_COLON,
                    DocLexer::T_EQUALS
                )
            );
        }

        // merge in the value
        return array_merge($tokens, $this->getPlainValue());
    }

    /**
     * Returns the tokens for the next assigned (key/value) value.
     *
     * @return array The tokens.
     */
    private function getAssignedValue()
    {
        $this->match(DocLexer::T_IDENTIFIER);

        $tokens = array(
            array(DocLexer::T_IDENTIFIER, $this->lexer->token['value']),
            array(DocLexer::T_EQUALS),
        );

        $this->match(DocLexer::T_EQUALS);

        return array_merge($tokens, $this->getPlainValue());
    }

    /**
     * Returns the current constant value for the current annotation.
     *
     * @return array The tokens.
     */
    private function getConstant()
    {
        $identifier = $this->getIdentifier();
        $tokens = array();

        // check for a special constant type
        switch (strtolower($identifier)) {
            case 'true':
                $tokens[] = array(DocLexer::T_TRUE, $identifier);
                break;
            case 'false':
                $tokens[] = array(DocLexer::T_FALSE, $identifier);
                break;
            case 'null':
                $tokens[] = array(DocLexer::T_NULL, $identifier);
                break;
            default:
                $tokens[] = array(DocLexer::T_IDENTIFIER, $identifier);
        }

        return $tokens;
    }

    /**
     * Returns the next identifier.
     *
     * @return string The identifier.
     *
     * @throws Exception
     * @throws SyntaxException If a syntax error is found.
     */
    private function getIdentifier()
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

            $name .= '\\' . $this->lexer->token['value'];
        }

        return $name;
    }

    /**
     * Returns the tokens for the next "plain" value.
     *
     * @return array The tokens.
     *
     * @throws Exception
     * @throws SyntaxException If a syntax error is found.
     */
    private function getPlainValue()
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

        $tokens = array();

        // determine type, or throw syntax error if unrecognized
        switch ($this->lexer->lookahead['type']) {
            case DocLexer::T_FALSE:
            case DocLexer::T_FLOAT:
            case DocLexer::T_INTEGER:
            case DocLexer::T_NULL:
            case DocLexer::T_STRING:
            case DocLexer::T_TRUE:
                $this->match($this->lexer->lookahead['type']);

                $tokens[] = array(
                    $this->lexer->token['type'],
                    $this->lexer->token['value']
                );

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
     * @return array The tokens.
     */
    private function getValue()
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
     * @return array The tokens.
     *
     * @throws Exception
     * @throws SyntaxException If a syntax error is found.
     */
    private function getValues()
    {
        $tokens = array();

        // check if a value list is given
        if ($this->lexer->isNextToken(DocLexer::T_OPEN_PARENTHESIS)) {
            $this->match(DocLexer::T_OPEN_PARENTHESIS);

            $tokens[] = array(DocLexer::T_OPEN_PARENTHESIS);

            // skip if we are given an empty list: @example()
            if ($this->lexer->isNextToken(DocLexer::T_CLOSE_PARENTHESIS)) {
                $this->match(DocLexer::T_CLOSE_PARENTHESIS);

                $tokens[] = array(DocLexer::T_CLOSE_PARENTHESIS);

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

            $tokens[] = array(DocLexer::T_COMMA);

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

        $tokens[] = array(DocLexer::T_CLOSE_PARENTHESIS);

        return $tokens;
    }

    /**
     * Matches the next token and advances.
     *
     * @param integer $token The next token to match.
     *
     * @return array|null TRUE if the next token matches, FALSE if not.
     *
     * @throws Exception
     * @throws SyntaxException If a syntax error is found.
     */
    private function match($token)
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
     * @param array $tokens The list of tokens.
     *
     * @return boolean TRUE if the next token matches, FALSE if not.
     *
     * @throws Exception
     * @throws SyntaxException If a syntax error is found.
     */
    private function matchAny(array $tokens)
    {
        if (!$this->lexer->isNextTokenAny($tokens)) {
            throw SyntaxException::expectedToken(
                implode(
                    ' or ',
                    array_map(array($this->lexer, 'getLiteral'), $tokens)
                ),
                null,
                $this->lexer
            );
        }

        return $this->lexer->moveNext();
    }
}
