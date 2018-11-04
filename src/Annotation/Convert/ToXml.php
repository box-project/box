<?php

namespace KevinGH\Box\Annotation\Convert;

use Doctrine\Common\Annotations\DocLexer;
use DOMDocument;
use DOMElement;
use DOMText;
use KevinGH\Box\Annotation\Exception\Exception;
use KevinGH\Box\Annotation\Exception\InvalidArgumentException;
use KevinGH\Box\Annotation\Exception\InvalidXmlException;
use KevinGH\Box\Annotation\Tokens;

/**
 * The path to the annotations schema.
 */
define(
    'HERRERA_ANNOTATIONS_SCHEMA',
    __DIR__ . '/../../../../../res/annotations.xsd'
);

/**
 * Converts a series of tokens into an XML document.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class ToXml extends AbstractConvert
{
    /**
     * @see ANNOTATIONS_SCHEMA
     */
    const SCHEMA = HERRERA_ANNOTATIONS_SCHEMA;

    /**
     * The annotations XML document.
     *
     * @var DOMDocument
     */
    protected $result;

    /**
     * The current annotation element.
     *
     * @var DOMElement
     */
    private $current;

    /**
     * The current list depth.
     *
     * @var integer
     */
    private $depth = 0;

    /**
     * The references to elements.
     *
     * @var array<DOMElement>
     */
    private $references;

    /**
     * Validates the annotations XML document.
     *
     * @param DOMDocument|string $input The document to validate.
     *
     * @throws Exception
     * @throws InvalidArgumentException If $input is not valid.
     * @throws InvalidXmlException      If the document is not valid.
     */
    public static function validate($input)
    {
        if (is_string($input)) {
            $doc = new DOMDocument();
            $doc->preserveWhiteSpace = false;

            if (!@$doc->loadXML($input)) {
                throw InvalidXmlException::lastError();
            }

            $input = $doc;
        } elseif (!($input instanceof DOMDocument)) {
            throw InvalidArgumentException::create(
                'The $input argument must be an instance of DOMDocument, %s given.',
                gettype($input)
            );
        }

        if (!@$input->schemaValidate(self::SCHEMA)) {
            throw InvalidXmlException::lastError();
        }
    }

    /**
     * @override
     */
    protected function handle()
    {
        $token = $this->tokens->current();

        switch ($token[0]) {
            case DocLexer::T_AT:
                $this->start();

                break;
            case DocLexer::T_CLOSE_CURLY_BRACES:
                $this->endList();

                break;
            case DocLexer::T_CLOSE_PARENTHESIS:
                $this->endValues();
                $this->end();

                break;
            case DocLexer::T_OPEN_CURLY_BRACES:
                $this->startList();

                break;
            case DocLexer::T_OPEN_PARENTHESIS:
                $this->startValues();

                break;
            case DocLexer::T_FALSE:
            case DocLexer::T_FLOAT:
            case DocLexer::T_IDENTIFIER:
            case DocLexer::T_INTEGER:
            case DocLexer::T_NULL:
            case DocLexer::T_STRING:
            case DocLexer::T_TRUE:
                $this->add();

                break;
            case DocLexer::T_COLON:
            case DocLexer::T_COMMA:
            case DocLexer::T_EQUALS:
                break;
        }
    }

    /**
     * @override
     */
    protected function reset(Tokens $tokens)
    {
        $this->current = null;
        $this->depth = 0;
        $this->references = array();
        $this->tokens = $tokens;

        $this->result = new DOMDocument();
        $this->result->formatOutput = true;
        $this->result->preserveWhiteSpace = false;

        $this->result->loadXML('<annotations/>');
    }

    /**
     * Adds a value to the current list or annotation.
     */
    private function add()
    {
        // skip if it's a key
        if ((null !== ($check = $this->tokens->getToken($this->tokens->key() + 1)))
            && ((DocLexer::T_COLON === $check[0])
                || (DocLexer::T_EQUALS === $check[0]))) {
            return;
        }

        $token = $this->tokens->current();
        $value = $this->result->createElement('value');

        if (null !== ($key = $this->key())) {
            $value->setAttribute('key', $key);
        }

        $this->current->appendChild($value);

        $text = null;
        $type = null;

        switch ($token[0]) {
            case DocLexer::T_FALSE:
                $text = '0';
                $type = 'boolean';

                break;
            case DocLexer::T_FLOAT:
                $text = $token[1];
                $type = 'float';

                break;
            case DocLexer::T_IDENTIFIER:
                $text = $token[1];
                $type = 'constant';

                break;
            case DocLexer::T_INTEGER:
                $text = $token[1];
                $type = 'integer';

                break;
            case DocLexer::T_NULL:
                $type = 'null';

                break;
            case DocLexer::T_STRING:
                $text = $token[1];
                $type = 'string';

                break;
            case DocLexer::T_TRUE:
                $text = '1';
                $type = 'boolean';

                break;
        }

        $value->setAttribute('type', $type);

        if (null !== $text) {
            $value->appendChild(new DOMText($text));
        }
    }

    /**
     * Ends the current annotation.
     */
    private function end()
    {
        $this->current = array_pop($this->references);
    }

    /**
     * Ends accepting a nested list of values.
     */
    private function endList()
    {
        $this->depth--;

        $this->current = array_pop($this->references);
    }

    /**
     * Ends accepting values for the current annotation.
     */
    private function endValues()
    {
        $this->depth--;
    }

    /**
     * Finds the key and returns it.
     *
     * @return string The key.
     */
    private function key()
    {
        $offset = $this->tokens->key();
        $op = $this->tokens->getToken($offset - 1);

        if ($op
            && isset($this->tokens[$offset - 2])
            && ((DocLexer::T_COLON === $op[0])
                || (DocLexer::T_EQUALS === $op[0]))) {
            return $this->tokens->getValue($offset - 2);
        }

        return null;
    }

    /**
     * Begins a new annotation.
     */
    private function start()
    {
        $annotation = $this->result->createElement('annotation');
        $offset = $this->tokens->key();

        // set the key, if applicable
        if (null !== ($key = $this->key())) {
            $annotation->setAttribute('key', $key);
        }

        $annotation->setAttribute(
            'name',
            $this->tokens->getValue($offset + 1)
        );

        $this->tokens->next();

        // append to list, if applicable
        if ($this->depth) {
            $this->current->appendChild($annotation);

            $this->references[] = $this->current;

            // append to document
        } else {
            $this->result->documentElement->appendChild($annotation);
        }

        $this->current = $annotation;

        if ((null !== ($next = $this->tokens->getToken($offset + 2)))
            && (DocLexer::T_OPEN_PARENTHESIS !== $next[0])) {
            $this->end();
        }
    }

    /**
     * Beings accepting a nested list of values.
     */
    private function startList()
    {
        $this->depth++;

        $values = $this->result->createElement('values');

        // set the key, if applicable
        if (null !== ($key = $this->key())) {
            $values->setAttribute('key', $key);
        }

        $this->current->appendChild($values);

        $this->references[] = $this->current;
        $this->current = $values;
    }

    /**
     * Begins accepting values for the current annotation.
     */
    private function startValues()
    {
        $this->depth++;
    }
}
