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

namespace KevinGH\Box\PhpScoper;

use Humbug\PhpScoper\Scoper as PhpScoper;
use Humbug\PhpScoper\Whitelist;

/**
 * @private
 */
final class SimpleScoper implements Scoper
{
    private $scoper;
    private $prefix;
    private $whitelist;
    private $patchers;

    public function __construct(PhpScoper $scoper, string $prefix, Whitelist $whitelist, array $patchers)
    {
        $this->scoper = $scoper;
        $this->prefix = $prefix;
        $this->whitelist = $whitelist;
        $this->patchers = $patchers;
    }

    /**
     * {@inheritdoc}
     */
    public function scope(string $filePath, string $contents): string
    {
        return $this->scoper->scope(
            $filePath,
            $contents,
            $this->prefix,
            $this->patchers,
            $this->whitelist
        );
    }

    /**
     * {@inheritdoc}
     */
    public function changeWhitelist(Whitelist $whitelist): void
    {
        $this->whitelist = $whitelist;
    }

    /**
     * {@inheritdoc}
     */
    public function getWhitelist(): Whitelist
    {
        return $this->whitelist;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
}
