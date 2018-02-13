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

namespace KevinGH\Box;

use Assert\Assertion;
use Herrera\Annotations\Tokenizer;
use function implode;
use KevinGH\Box\Compactor\Php;
use function str_replace;

/**
 * Generates a new PHP bootstrap loader stub for a PHAR.
 */
final class StubGenerator
{
    private const STUB_TEMPLATE = <<<'STUB'
__BOX_SHEBANG__
<?php

__BOX_BANNER__

__BOX_PHAR_CONFIG__

__HALT_COMPILER(); ?>

STUB;

    /**
     * @var string The alias to be used in "phar://" URLs
     */
    private $alias;

    /**
     * @var null|string The top header comment banner text
     */
    private $banner = <<<'BANNER'
Generated by Box.

@link https://github.com/humbug/box
BANNER;

    /**
     * @var null|string The location within the PHAR of index script
     */
    private $index;

    /**
     * @var bool Use the Phar::interceptFileFuncs() method?
     */
    private $intercept = false;

    /**
     * @var string|null The shebang line
     */
    private $shebang = '#!/usr/bin/env php';

    /**
     * Creates a new instance of the stub generator.
     *
     * @return StubGenerator the stub generator
     */
    public static function create()
    {
        return new static();
    }

    /**
     * @return string The stub
     */
    public function generate(): string
    {
        $stub = self::STUB_TEMPLATE;

        $stub = str_replace(
            "__BOX_SHEBANG__\n",
            null === $this->shebang ? '' : $this->shebang."\n",
            $stub
        );

        $stub = str_replace(
            "__BOX_BANNER__\n",
            $this->generateBannerStmt(),
            $stub
        );

        $stub = str_replace(
            "__BOX_PHAR_CONFIG__\n",
            (string) $this->generatePharConfigStmt(),
            $stub
        );

        return $stub;
    }

    public function alias(string $alias): self
    {
        $this->alias = $alias;

        return $this;
    }

    public function banner(?string $banner): self
    {
        $this->banner = $banner;

        return $this;
    }

    public function index(?string $index): self
    {
        $this->index = $index;

        return $this;
    }

    public function intercept(bool $intercept): self
    {
        $this->intercept = $intercept;

        return $this;
    }

    public function shebang(?string $shebang): self
    {
        if (null !== $shebang) {
            Assertion::notEmpty($shebang, 'Cannot use an empty string for the shebang.');
        }

        $this->shebang = $shebang;

        return $this;
    }

    /**
     * Escapes an argument so it can be written as a string in a call.
     *
     * @param string $arg
     * @param string $quote
     *
     * @return string The escaped argument
     */
    private function arg(string $arg, string $quote = "'"): string
    {
        return $quote.addcslashes($arg, $quote).$quote;
    }

    private function getAliasStmt(): ?string
    {
        return null !== $this->alias ? 'Phar::mapPhar('.$this->arg($this->alias).');' : null;
    }

    /**
     * @return string the processed banner
     */
    private function generateBannerStmt(): string
    {
        // TODO: review how the banner is processed. Right now the doc says it can be a string
        // already enclosed in comments and if not it will be enclosed automatically.
        //
        // What needs to be done here?
        // - Test with a simple one liner banner
        // - Test with a banner enclosed in comments
        // - Test with a banner enclosed in phpdoc
        //
        // Then comes the question of multiline banners: I guess it works if contains `\n`?
        // Need tests for that anyway.
        //
        // Maybe a more user-friendly way to deal with multi-line banners would be to allow
        // an array of strings instead of just a string.
        //

        $banner = "/*\n * ";
        $banner .= str_replace(
            " \n",
            "\n",
            str_replace("\n", "\n * ", $this->banner)
        );

        $banner .= "\n */";

        return $banner."\n";
    }

    /**
     * @return string[] The sections of the stub that use the PHAR class
     */
    private function generatePharConfigStmt(): ?string
    {
        $stub = [];

        if (null !== $aliasStmt = $this->getAliasStmt()) {
            $stub[] = $aliasStmt;
        }

        if ($this->intercept) {
            $stub[] = 'Phar::interceptFileFuncs();';
        }

        if ($this->index) {
            $stub[] = "require 'phar://' . __FILE__ . '/{$this->index}';";
        }

        if ([] === $stub) {
            return "// No PHAR config\n";
        }

        return implode("\n", $stub)."\n";
    }
}
