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

use function array_pop;
use function array_slice;
use function array_splice;
use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use KevinGH\Box\Annotation\DocblockAnnotationParser;
use KevinGH\Box\Annotation\InvalidToken;
use function ltrim;
use function preg_replace;
use RuntimeException;
use function str_repeat;
use function strpos;
use function substr;
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
 * @author Juliette Reinders Folmer <boxproject_nospam@adviesenzo.nl>
 * @author Alessandro Chitolina <alekitto@gmail.com>
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
        $tokens = token_get_all($contents);
        $tokenCount = count($tokens);

        for ($index = 0; $index < $tokenCount; ++$index) {
            $token = $tokens[$index];
            if (is_string($token)) {
                $output .= $token;
            } elseif (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                if (0 === strpos($token[1], '#[')) {
                    // This is, in all likelyhood, the start of a PHP >= 8.0 attribute.
                    // Note: $tokens may be updated by reference as well!
                    $retokenized = $this->retokenizeAttribute($tokens, $index);
                    if (null !== $retokenized) {
                        array_splice($tokens, $index, 1, $retokenized);
                        $tokenCount = count($tokens);
                    }

                    $attributeCloser = $this->findAttributeCloser($tokens, $index);

                    if (is_int($attributeCloser)) {
                        $output .= '#[';
                    } else {
                        // Turns out this was not an attribute. Treat it as a plain comment.
                        $output .= str_repeat("\n", substr_count($token[1], "\n"));
                    }
                } elseif (false !== strpos($token[1], '@')) {
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
                $whitespace = $token[1];
                $previousIndex = ($index - 1);

                // Handle whitespace potentially being split into two tokens after attribute retokenization.
                if (isset($tokens[$index + 1])
                    && is_array($tokens[$index + 1])
                    && T_WHITESPACE === $tokens[$index + 1][0]
                ) {
                    $whitespace .= $tokens[$index + 1][1];
                    ++$index;
                }

                // reduce wide spaces
                $whitespace = preg_replace('{[ \t]+}', ' ', $whitespace);

                // normalize newlines to \n
                $whitespace = preg_replace('{(?:\r\n|\r|\n)}', "\n", $whitespace);

                // If the new line was split off from the whitespace token due to it being included in
                // the previous (comment) token (PHP < 8), remove leading spaces.
                if (is_array($tokens[$previousIndex])
                    && T_COMMENT === $tokens[$previousIndex][0]
                    && false !== strpos($tokens[$previousIndex][1], "\n")
                ) {
                    $whitespace = ltrim($whitespace, ' ');
                }

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

    private function findAttributeCloser(array $tokens, int $opener): ?int
    {
        $tokenCount = count($tokens);
        $brackets = [$opener];
        $closer = null;

        for ($i = ($opener + 1); $i < $tokenCount; ++$i) {
            if (false === is_string($tokens[$i])) {
                continue;
            }

            // Allow for short arrays within attributes.
            if ('[' === $tokens[$i]) {
                $brackets[] = $i;

                continue;
            }

            if (']' === $tokens[$i]) {
                array_pop($brackets);
                if (empty($brackets)) {
                    $closer = $i;
                    break;
                }
            }
        }

        return $closer;
    }

    private function retokenizeAttribute(array &$tokens, int $opener): ?array
    {
        $token = $tokens[$opener];
        $attributeBody = substr($token[1], 2);
        $subTokens = @token_get_all('<?php '.$attributeBody);

        // Replace the PHP open tag with the attribute opener as a simple token.
        array_splice($subTokens, 0, 1, ['#[']);

        $closer = $this->findAttributeCloser($subTokens, 0);

        // Multi-line attribute or attribute containing something which looks like a PHP close tag.
        // Retokenize the rest of the file after the attribute opener.
        if (null === $closer) {
            foreach (array_slice($tokens, ($opener + 1)) as $token) {
                if (is_array($token)) {
                    $attributeBody .= $token[1];
                } else {
                    $attributeBody .= $token;
                }
            }

            $subTokens = @token_get_all('<?php '.$attributeBody);
            array_splice($subTokens, 0, 1, ['#[']);

            $closer = $this->findAttributeCloser($subTokens, 0);

            if (null !== $closer) {
                array_splice($tokens, ($opener + 1), count($tokens), array_slice($subTokens, ($closer + 1)));
                $subTokens = array_slice($subTokens, 0, ($closer + 1));
            }
        }

        if (null === $closer) {
            return null;
        }

        return $subTokens;
    }
}
