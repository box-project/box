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

use Humbug\PhpScoper\Console\Configuration as PhpScoperConfiguration;
use Humbug\PhpScoper\Scoper;
use KevinGH\Box\Compactor;

final class PhpScoper implements Compactor
{
    private $scoper;
    private $config;

    public function __construct(Scoper $scoper, PhpScoperConfiguration $config)
    {
        $this->scoper = $scoper;
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function compact(string $file, string $contents): string
    {
        return $this->scoper->scope(
            $file,
            $contents,
            '_HumbugBox',
            $this->config->getPatchers(),
            $this->config->getWhitelist()
        );
    }

    public function getConfiguration(): PhpScoperConfiguration
    {
        return $this->config;
    }
}
