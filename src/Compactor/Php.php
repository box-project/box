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

use KevinGH\Box\Annotation\CompactedFormatter;
use KevinGH\Box\Annotation\DocblockAnnotationParser;
use phpDocumentor\Reflection\DocBlockFactory;
use PhpToken;
use RuntimeException;
use Webmozart\Assert\Assert;
use function array_pop;
use function array_slice;
use function array_splice;
use function count;
use function is_int;
use function ltrim;
use function preg_replace;
use function str_repeat;
use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_WHITESPACE;

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
 *
 * @private
 */
final class Php extends FileExtensionCompactor
{
    /**
     * @param list<string> $ignoredAnnotations
     */
    public static function create(array $ignoredAnnotations): self
    {
        return new self(
            new DocblockAnnotationParser(
                DocBlockFactory::createInstance(),
                new CompactedFormatter(),
                $ignoredAnnotations,
            ),
        );
    }

    public function __construct(
        private readonly ?DocblockAnnotationParser $annotationParser,
        array $extensions = ['php'],
    ) {
        parent::__construct($extensions);
    }

    protected function compactContent(string $contents): string
    {
        $output = '';
        $tokens = PhpToken::tokenize($contents);
        $tokenCount = count($tokens);

        for ($index = 0; $index < $tokenCount; ++$index) {
            $token = $tokens[$index];
            $tokenText = $token->text;

            if ($token->is([T_COMMENT, T_DOC_COMMENT])) {
                if (str_starts_with($tokenText, '#[')) {
                    // This is, in all likelihood, the start of a PHP >= 8.0 attribute.
                    // Note: $tokens may be updated by reference as well!
                    $retokenized = $this->retokenizeAttribute($tokens, $index);

                    if (null !== $retokenized) {
                        array_splice($tokens, $index, 1, $retokenized);
                        $tokenCount = count($tokens);
                    }

                    $attributeCloser = self::findAttributeCloser($tokens, $index);

                    if (is_int($attributeCloser)) {
                        $output .= '#[';
                    } else {
                        // Turns out this was not an attribute. Treat it as a plain comment.
                        $output .= str_repeat("\n", mb_substr_count($tokenText, "\n"));
                    }
                } elseif (str_contains($tokenText, '@')) {
                    try {
                        $output .= $this->compactAnnotations($tokenText);
                    } catch (RuntimeException) {
                        $output .= $tokenText;
                    }
                } else {
                    $output .= str_repeat("\n", mb_substr_count($tokenText, "\n"));
                }
            } elseif ($token->is(T_WHITESPACE)) {
                $whitespace = $tokenText;
                $previousIndex = ($index - 1);

                // Handle whitespace potentially being split into two tokens after attribute retokenization.
                $nextToken = $tokens[$index + 1] ?? null;

                if (null !== $nextToken
                    && $nextToken->is(T_WHITESPACE)
                ) {
                    $whitespace .= $nextToken->text;
                    ++$index;
                }

                // reduce wide spaces
                $whitespace = preg_replace('{[ \t]+}', ' ', $whitespace);

                // normalize newlines to \n
                $whitespace = preg_replace('{(?:\r\n|\r|\n)}', "\n", $whitespace);

                // If the new line was split off from the whitespace token due to it being included in
                // the previous (comment) token (PHP < 8), remove leading spaces.

                $previousToken = $tokens[$previousIndex];

                if ($previousToken->is(T_COMMENT)
                    && str_contains($previousToken->text, "\n")
                ) {
                    $whitespace = ltrim($whitespace, ' ');
                }

                // trim leading spaces
                $whitespace = preg_replace('{\n +}', "\n", $whitespace);

                $output .= $whitespace;
            } else {
                $output .= $tokenText;
            }
        }

        return $output;
    }

    private function compactAnnotations(string $docblock): string
    {
        if (null === $this->annotationParser) {
            return $docblock;
        }

        $breaksNbr = mb_substr_count($docblock, "\n");

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

    /**
     * @param list<PhpToken> $tokens
     */
    private static function findAttributeCloser(array $tokens, int $opener): ?int
    {
        $tokenCount = count($tokens);
        $brackets = [$opener];
        $closer = null;

        for ($i = ($opener + 1); $i < $tokenCount; ++$i) {
            $tokenText = $tokens[$i]->text;

            // Allow for short arrays within attributes.
            if ('[' === $tokenText) {
                $brackets[] = $i;

                continue;
            }

            if (']' === $tokenText) {
                array_pop($brackets);

                if (0 === count($brackets)) {
                    $closer = $i;
                    break;
                }
            }
        }

        return $closer;
    }

    /**
     * @param non-empty-list<PhpToken> $tokens
     */
    private function retokenizeAttribute(array &$tokens, int $opener): ?array
    {
        Assert::keyExists($tokens, $opener);

        $token = $tokens[$opener];
        $attributeBody = mb_substr($token->text, 2);
        $subTokens = PhpToken::tokenize('<?php '.$attributeBody);

        // Replace the PHP open tag with the attribute opener as a simple token.
        array_splice($subTokens, 0, 1, ['#[']);

        $closer = self::findAttributeCloser($subTokens, 0);

        // Multi-line attribute or attribute containing something which looks like a PHP close tag.
        // Retokenize the rest of the file after the attribute opener.
        if (null === $closer) {
            foreach (array_slice($tokens, $opener + 1) as $token) {
                $attributeBody .= $token->text;
            }

            $subTokens = PhpToken::tokenize('<?php '.$attributeBody);
            array_splice($subTokens, 0, 1, ['#[']);

            $closer = self::findAttributeCloser($subTokens, 0);

            if (null !== $closer) {
                array_splice(
                    $tokens,
                    $opener + 1,
                    count($tokens),
                    array_slice($subTokens, $closer + 1),
                );

                $subTokens = array_slice($subTokens, 0, $closer + 1);
            }
        }

        if (null === $closer) {
            return null;
        }

        return $subTokens;
    }
}
