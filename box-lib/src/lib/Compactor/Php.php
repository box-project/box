<?php

namespace Herrera\Box\Compactor;

use Doctrine\Common\Annotations\DocLexer;
use Herrera\Annotations\Convert\ToString;
use Herrera\Annotations\Tokenizer;
use Herrera\Annotations\Tokens;

/**
 * A PHP source code compactor copied from Composer.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Php extends Compactor
{
    /**
     * The annotation tokens converter.
     *
     * @var ToString
     */
    private $converter;

    /**
     * The default list of supported file extensions.
     *
     * @var array
     */
    protected $extensions = array('php');

    /**
     * The annotations tokenizer.
     *
     * @var Tokenizer
     */
    private $tokenizer;

    /**
     * {@inheritDoc}
     */
    public function compact($contents)
    {
        $output = '';
        foreach (token_get_all($contents) as $token) {
            if (is_string($token)) {
                $output .= $token;
            } elseif (in_array($token[0], array(T_COMMENT, T_DOC_COMMENT))) {
                if ($this->tokenizer && (false !== strpos($token[1], '@'))) {
                    $output .= $this->compactAnnotations($token[1]);
                } else {
                    $output .= str_repeat("\n", substr_count($token[1], "\n"));
                }
            } elseif (T_WHITESPACE === $token[0]) {
                // reduce wide spaces
                $whitespace = preg_replace('{[ \t]+}', ' ', $token[1]);
                // normalize newlines to \n
                $whitespace = preg_replace('{(?:\r\n|\r|\n)}', "\n", $whitespace);
                // trim leading spaces
                $whitespace = preg_replace('{\n +}', "\n", $whitespace);
                $output .= $whitespace;
            } else {
                $output .= $token[1];
            }
        }

        return $output;
    }

    /**
     * Sets the annotations tokenizer.
     *
     * @param Tokenizer $tokenizer The tokenizer.
     */
    public function setTokenizer(Tokenizer $tokenizer)
    {
        if (null === $this->converter) {
            $this->converter = new ToString();
        }

        $this->tokenizer = $tokenizer;
    }

    /**
     * Compacts the docblock and its annotations.
     *
     * @param string $docblock The docblock.
     *
     * @return string The compacted docblock.
     */
    private function compactAnnotations($docblock)
    {
        $annotations = array();
        $index = -1;
        $inside = 0;
        $tokens = $this->tokenizer->parse($docblock);

        if (empty($tokens)) {
            return str_repeat("\n", substr_count($docblock, "\n"));
        }

        foreach ($tokens as $token) {
            if ((0 === $inside) && (DocLexer::T_AT === $token[0])) {
                $index++;
            } elseif (DocLexer::T_OPEN_PARENTHESIS === $token[0]) {
                $inside++;
            } elseif (DocLexer::T_CLOSE_PARENTHESIS === $token[0]) {
                $inside--;
            }

            if (!isset($annotations[$index])) {
                $annotations[$index] = array();
            }

            $annotations[$index][] = $token;
        }

        $breaks = substr_count($docblock, "\n");
        $docblock = "/**";

        foreach ($annotations as $annotation) {
            $annotation = new Tokens($annotation);
            $docblock .= "\n" . $this->converter->convert($annotation);
        }

        $breaks -= count($annotations);

        if ($breaks > 0) {
            $docblock .= str_repeat("\n", $breaks - 1);
            $docblock .= "\n*/";
        } else {
            $docblock .= ' */';
        }

        return $docblock;
    }
}
