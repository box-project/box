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

use function count;
use function in_array;
use function is_string;
use KevinGH\Box\Annotation\DocblockAnnotationParser;
use KevinGH\Box\Annotation\InvalidToken;
use function preg_replace;
use RuntimeException;
use function str_repeat;
use function strpos;
use function substr_count;
use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_WHITESPACE;
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
        $output = '';

        foreach (token_get_all($contents) as $token) {
            if (is_string($token)) {
                $output .= $token;
            } elseif (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                if (false !== strpos($token[1], '@')) {
                    try {
                        $output .= $this->compactAnnotations($token[1]);
                    } catch (InvalidToken $exception) {
                        // This exception is due to the dumper to be out of sync with the current grammar and/or the
                        // grammar being incomplete. In both cases throwing here is better in order to identify and
                        // this those cases instead of silently failing.

                        throw $exception;
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
        $breaksNbr = substr_count($docblock, "\n");

        $annotations = $this->annotationParser->parse($docblock);

        if ([] === $annotations) {
            return str_repeat("\n", $breaksNbr);
        }

        $compactedDocblock = '/**';

        foreach ($annotations as $annotation) {
            $compactedDocblock .= "\n".$annotation;
        }

        $breaksNbr -= count($annotations);

        if ($breaksNbr > 0) {
            $compactedDocblock .= str_repeat("\n", $breaksNbr - 1);
            $compactedDocblock .= "\n*/";
        } else {
            // A space is required here to avoid having /***/
            $compactedDocblock .= ' */';
        }

        return $compactedDocblock;
    }
}
