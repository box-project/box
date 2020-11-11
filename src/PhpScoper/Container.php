<?php

declare(strict_types=1);

/*
 * This file is part of the humbug/php-scoper package.
 *
 * Copyright (c) 2017 Théo FIDRY <theo.fidry@gmail.com>,
 *                    Pádraic Brady <padraic.brady@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KevinGH\Box\PhpScoper;

use Humbug\PhpScoper\PhpParser\TraverserFactory;
use Humbug\PhpScoper\Reflector;
use Humbug\PhpScoper\Scoper;
use Humbug\PhpScoper\Scoper\Composer\InstalledPackagesScoper;
use Humbug\PhpScoper\Scoper\Composer\JsonFileScoper;
use Humbug\PhpScoper\Scoper\NullScoper;
use Humbug\PhpScoper\Scoper\PatchScoper;
use Humbug\PhpScoper\Scoper\PhpScoper;
use Humbug\PhpScoper\Scoper\SymfonyScoper;
use PhpParser\Lexer;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Copied for testing purpose
 */
final class Container
{
    private $parser;
    private $reflector;
    private $scoper;

    public function getScoper(): Scoper
    {
        if (null === $this->scoper) {
            $this->scoper = new PatchScoper(
                new PhpScoper(
                    $this->getParser(),
                    new JsonFileScoper(
                        new InstalledPackagesScoper(
                            new SymfonyScoper(
                                new NullScoper()
                            )
                        )
                    ),
                    new TraverserFactory($this->getReflector())
                )
            );
        }

        return $this->scoper;
    }

    public function getParser(): Parser
    {
        if (null === $this->parser) {
            $phpVersion = Lexer\Emulative::PHP_7_3;
            if (PHP_VERSION_ID >= 80000) {
                $phpVersion = Lexer\Emulative::PHP_8_0;
            } elseif (PHP_VERSION_ID >= 70400) {
                $phpVersion = Lexer\Emulative::PHP_7_4;
            }

            $this->parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7, new Lexer\Emulative(['phpVersion' => $phpVersion]));
        }

        return $this->parser;
    }

    public function getReflector(): Reflector
    {
        if (null === $this->reflector) {
            $this->reflector = new Reflector();
        }

        return $this->reflector;
    }
}
