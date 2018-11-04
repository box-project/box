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

use Doctrine\Common\Annotations\DocLexer;
use KevinGH\Box\Annotation\Tokens;

/**
 * Converts a series of tokens into a string representation.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class ToString extends AbstractConvert
{
    /**
     * The line break character(s).
     *
     * @var string
     */
    private $break = "\n";

    /**
     * The indentation character.
     *
     * @var string
     */
    private $char = ' ';

    /**
     * The current indentation level.
     *
     * @var int
     */
    private $level;

    /**
     * The flag used to add a space after a colon (assignment).
     *
     * @var bool
     */
    private $space = false;

    /**
     * The token to character map.
     *
     * @var array
     */
    private static $map = [
        DocLexer::T_AT => '@',
        DocLexer::T_CLOSE_CURLY_BRACES => '}',
        DocLexer::T_CLOSE_PARENTHESIS => ')',
        DocLexer::T_COLON => ':',
        DocLexer::T_COMMA => ',',
        DocLexer::T_EQUALS => '=',
        DocLexer::T_NAMESPACE_SEPARATOR => '\\',
        DocLexer::T_OPEN_CURLY_BRACES => '{',
        DocLexer::T_OPEN_PARENTHESIS => '(',
    ];

    /**
     * The indentation size.
     *
     * @var int
     */
    private $size = 0;

    /**
     * Sets the line break character(s) used for indentation.
     *
     * @param string $break the character(s)
     *
     * @return ToString the converter
     */
    public function setBreakChar($break): self
    {
        $this->break = $break;

        return $this;
    }

    /**
     * Sets the repeated indentation character.
     *
     * @param string $char the character
     *
     * @return ToString the converter
     */
    public function setIndentChar($char): self
    {
        $this->char = $char;

        return $this;
    }

    /**
     * Sets the size of the indentation.
     *
     * @param int $size the size
     *
     * @return ToString the converter
     */
    public function setIndentSize($size): self
    {
        $this->size = $size;

        return $this;
    }

    /**
     * Sets the flag that determines if a space is added after a colon.
     *
     * @param bool $space Add the space?
     *
     * @return ToString the converter
     */
    public function useColonSpace($space): self
    {
        $this->space = $space;

        return $this;
    }

    /**
     * Processes the current token.
     */
    protected function handle(): void
    {
        $token = $this->tokens->current();

        $this->preIndent();

        if (isset(self::$map[$token[0]])) {
            $this->result .= self::$map[$token[0]];
        } else {
            if (DocLexer::T_STRING === $token[0]) {
                $this->result .= '"'.$token[1].'"';
            } else {
                $this->result .= $token[1];
            }
        }

        $this->postIndent();
    }

    /**
     * {@inheritdoc}
     */
    protected function reset(Tokens $tokens): void
    {
        $this->level = 0;
        $this->result = '';
        $this->tokens = $tokens;

        $tokens->rewind();
    }

    /**
     * Adds indentation to the result.
     *
     * @param bool $force Force add a line break?
     */
    private function indent($force = false): void
    {
        if ($this->size || $force) {
            $this->result .= $this->break;
        }

        if ($this->size) {
            $this->result .= str_repeat(
                $this->char,
                $this->size * $this->level
            );
        }
    }

    /**
     * Handles indentation after the current token.
     */
    private function postIndent(): void
    {
        $next = $this->tokens->getId($this->tokens->key() + 1);

        switch ($this->tokens->getId()) {
            case DocLexer::T_COLON:
                if ($this->space) {
                    $this->result .= ' ';
                }

                break;
            case DocLexer::T_COMMA:
                if ((DocLexer::T_CLOSE_CURLY_BRACES !== $next)
                    && (DocLexer::T_CLOSE_PARENTHESIS !== $next)) {
                    $this->indent();
                }

                break;
            case DocLexer::T_OPEN_CURLY_BRACES:
                $this->level++;

                if (DocLexer::T_CLOSE_CURLY_BRACES !== $next) {
                    $this->indent();
                }

                break;
            case DocLexer::T_OPEN_PARENTHESIS:
                $this->level++;

                if (DocLexer::T_CLOSE_PARENTHESIS !== $next) {
                    $this->indent();
                }

                break;
        }
    }

    /**
     * Handles indentation before the current token.
     */
    private function preIndent(): void
    {
        $prev = $this->tokens->getId($this->tokens->key() - 1);

        switch ($this->tokens->getId()) {
            case DocLexer::T_AT:
                if ($prev
                    && (DocLexer::T_COLON !== $prev)
                    && (DocLexer::T_COMMA !== $prev)
                    && (DocLexer::T_EQUALS !== $prev)
                    && (DocLexer::T_OPEN_CURLY_BRACES !== $prev)
                    && (DocLexer::T_OPEN_PARENTHESIS !== $prev)) {
                    $this->indent(true);
                }

                break;
            case DocLexer::T_CLOSE_CURLY_BRACES:
                $this->level--;

                if (DocLexer::T_OPEN_CURLY_BRACES !== $prev) {
                    $this->indent();
                }

                break;
            case DocLexer::T_CLOSE_PARENTHESIS:
                $this->level--;

                if (DocLexer::T_OPEN_PARENTHESIS !== $prev) {
                    $this->indent();
                }

                break;
        }
    }
}
