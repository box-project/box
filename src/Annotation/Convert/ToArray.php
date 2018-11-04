<?php

namespace KevinGH\Box\Annotation\Convert;

use ArrayObject;
use Doctrine\Common\Annotations\DocLexer;
use KevinGH\Box\Annotation\Tokens;

/**
 * Converts a series of tokens into a simple array.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class ToArray extends AbstractConvert
{
    /**
     * The current reference.
     *
     * @var ArrayObject
     */
    private $current;

    /**
     * The stack of references.
     *
     * @var array<ArrayObject>
     */
    private $stack;

    /**
     * {@override}
     */
    protected function handle()
    {
        $offset = $this->tokens->key();

        switch ($this->tokens->getId()) {
            case DocLexer::T_AT:
                $this->start();

                break;
            case DocLexer::T_CLOSE_CURLY_BRACES:
            case DocLexer::T_CLOSE_PARENTHESIS:
                $this->current = array_pop($this->stack);

                break;
            case DocLexer::T_OPEN_CURLY_BRACES:
                $this->startList();

                break;
            case DocLexer::T_IDENTIFIER:
            case DocLexer::T_INTEGER:
            case DocLexer::T_STRING:
                $next = $this->tokens->getId($offset + 1);

                // skip if key
                if ((DocLexer::T_COLON === $next)
                    || (DocLexer::T_EQUALS === $next)) {
                    break;
                }

                // no break
            case DocLexer::T_FALSE:
            case DocLexer::T_FLOAT:
            case DocLexer::T_NULL:
            case DocLexer::T_TRUE:
                if (null === ($key = $this->tokens->getKey())) {
                    $this->current[] = $this->tokens->getValue();
                } else {
                    $this->current[$key] = $this->tokens->getValue();
                }

                break;
            case DocLexer::T_COLON:
            case DocLexer::T_COMMA:
            case DocLexer::T_EQUALS:
                break;
        }

        // convert on last token
        if (!isset($this->tokens[$this->tokens->key() + 1])) {
            $this->result = $this->finish($this->result);
        }
    }

    /**
     * {@override}
     */
    protected function reset(Tokens $tokens)
    {
        $this->current = null;
        $this->result = array();
        $this->stack = array();
        $this->tokens = $tokens;

        $tokens->rewind();
    }

    /**
     * Finishes by converting ArrayObject to array.
     */
    private function finish($list)
    {
        if (is_array($list) || ($list instanceof ArrayObject)) {
            foreach ($list as $index => $item) {
                $list[$index] = $this->finish($item);
            }

            if ($list instanceof ArrayObject) {
                $list = $list->getArrayCopy();
            }
        } elseif (is_object($list)) {
            $list->values = $this->finish($list->values);
        }

        return $list;
    }

    /**
     * Begins a new annotation.
     */
    private function start()
    {
        $annotation = (object) array(
            'name' => $this->tokens->getValue($this->tokens->key() + 1),
            'values' => new ArrayObject(),
        );

        // skip the name token
        $this->tokens->next();

        $offset = $this->tokens->key();

        // nest, if necessary
        if ($this->current) {
            if (null === ($key = $this->tokens->getKey($offset - 1))) {
                $this->current[] = $annotation;
            } else {
                $this->current[$key] = $annotation;
            }

            $this->stack[] = $this->current;

            // add to root
        } else {
            $this->result[] = $annotation;
        }

        if (DocLexer::T_OPEN_PARENTHESIS
            === $this->tokens->getId($offset + 1)) {
            $this->current = $annotation->values;
        }
    }

    /**
     * Begins an array of values.
     */
    private function startList()
    {
        $list = new ArrayObject();

        if (null === ($key = $this->tokens->getKey())) {
            $this->current[] = $list;
        } else {
            $this->current[$key] = $list;
        }

        $this->stack[] = $this->current;
        $this->current = $list;
    }
}
