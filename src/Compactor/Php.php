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

use KevinGH\Box\Annotation\DocblockAnnotationParser;
use RuntimeException;
use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_WHITESPACE;
use function count;
use function in_array;
use function is_string;
use function preg_replace;
use function str_repeat;
use function strpos;
use function substr_count;
use function token_get_all;

/**
 * A PHP source code compactor copied from Composer.
 *
 * @see https://github.com/composer/composer/blob/a8df30c09be550bffc37ba540fb7c7f0383c3944/src/Composer/Compiler.php#L214
 *
 * @author Kevin Herrera <kevin@herrera.io>
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Théo Fidry <theo.fidry@gmail.com>
 * @private
 */
final class Php extends FileExtensionCompactor
{
    private $annotationParser;

    /**
     * {@inheritdoc}
     */
    public function __construct(DocblockAnnotationParser $annotationParser, array $extensions = ['php'])
    {
        parent::__construct($extensions);

        $this->annotationParser = $annotationParser;
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
                if (false !== strpos($token[1], '@')) {
                    try {
                        $output .= $this->compactAnnotations($token[1]);
                    } catch (RuntimeException $exception) {
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
        $breaks = substr_count($docblock, "\n");
        $compactedDocblock = '/**';

        $annotations = $this->annotationParser->parse($docblock);

        if ([] === $annotations) {
            return str_repeat("\n", $breaks);
        }

        foreach ($annotations as $annotation) {
            $compactedDocblock .= "\n".$annotation;
        }

        $breaks -= count($annotations);

        if ($breaks > 0) {
            $compactedDocblock .= str_repeat("\n", $breaks - 1);
            $compactedDocblock .= "\n*/";
        } else {
            $compactedDocblock .= ' */';
        }

        return $compactedDocblock;
    }
}
