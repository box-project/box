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

namespace KevinGH\Box\Compactor;

use Doctrine\Common\Annotations\DocLexer;
use Exception;
use Herrera\Annotations\Convert\ToString;
use Herrera\Annotations\Tokenizer;
use Herrera\Annotations\Tokens;

/**
 * A PHP source code compactor copied from Composer.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @see https://github.com/composer/composer/blob/a8df30c09be550bffc37ba540fb7c7f0383c3944/src/Composer/Compiler.php#L214
 */
final class Php extends FileExtensionCompactor
{
    private $converter;
    private $tokenizer;

    /**
     * {@inheritdoc}
     */
    public function __construct(Tokenizer $tokenizer, array $extensions = ['php'])
    {
        parent::__construct($extensions);

        $this->converter = new ToString();
        $this->tokenizer = $tokenizer;
    }

    /**
     * {@inheritdoc}
     */
    protected function compactContent(string $contents): string
    {
        // TODO: refactor this piece of code
        // - strip down blank spaces
        // - remove useless spaces
        // - strip down comments except Doctrine style annotations unless whitelisted -> BC break to document;
        //   Alternatively provide an easy way to strip down all "regular" annotations such as @package, @param
        //   & co.
        // - completely remove comments & docblocks if empty
        // TODO regarding the doc: it current has its own `annotations` entry. Maybe it would be best to
        // include it as a sub element of `compactors`
        $output = '';

        foreach (token_get_all($contents) as $token) {
            if (is_string($token)) {
                $output .= $token;
            } elseif (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                if ($this->tokenizer && (false !== strpos($token[1], '@'))) {
                    try {
                        $output .= $this->compactAnnotations($token[1]);
                    } catch (Exception $exception) {
                        $output .= $token[1];
                    }
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

    private function compactAnnotations(string $docblock): string
    {
        $annotations = [];
        $index = -1;
        $inside = 0;
        $tokens = $this->tokenizer->parse($docblock);

        if (empty($tokens)) {
            return str_repeat("\n", substr_count($docblock, "\n"));
        }

        foreach ($tokens as $token) {
            if ((0 === $inside) && (DocLexer::T_AT === $token[0])) {
                ++$index;
            } elseif (DocLexer::T_OPEN_PARENTHESIS === $token[0]) {
                ++$inside;
            } elseif (DocLexer::T_CLOSE_PARENTHESIS === $token[0]) {
                --$inside;
            }

            if (!isset($annotations[$index])) {
                $annotations[$index] = [];
            }

            $annotations[$index][] = $token;
        }

        $breaks = substr_count($docblock, "\n");
        $docblock = '/**';

        foreach ($annotations as $annotation) {
            $annotation = new Tokens($annotation);
            $docblock .= "\n".$this->converter->convert($annotation);
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
