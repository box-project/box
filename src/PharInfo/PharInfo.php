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

namespace KevinGH\Box\PharInfo;

use KevinGH\Box\Phar\PharInfo as NewPharInfo;
use Phar;
use PharData;
use UnexpectedValueException;
use function KevinGH\Box\unique_id;
use function realpath;
use function str_replace;

/**
 * @deprecated Deprecated since 4.4.1 in favour of \KevinGH\Box\Phar\PharInfo.
 */
final class PharInfo
{
    private NewPharInfo $decoratedPharInfo;

    private PharData|Phar $phar;

    public function __construct(private readonly string $pharFile)
    {
        $this->decoratedPharInfo = new NewPharInfo($pharFile);
    }

    public function equals(self $pharInfo): bool
    {
        return $this->decoratedPharInfo->equals($pharInfo->decoratedPharInfo);
    }

    public function getCompressionCount(): array
    {
        return $this->decoratedPharInfo->getFilesCompressionCount();
    }

    public function getPhar(): Phar|PharData
    {
        if (!isset($this->phar)) {
            try {
                $this->phar = new Phar($this->pharFile);
            } catch (UnexpectedValueException) {
                $this->phar = new PharData($this->pharFile);
            }
        }

        return $this->phar;
    }

    public function getRoot(): string
    {
        // Do not cache the result
        return 'phar://'.str_replace('\\', '/', realpath($this->phar->getPath())).'/';
    }

    public function getVersion(): string
    {
        return $this->decoratedPharInfo->getVersion();
    }

    public function getNormalizedMetadata(): ?string
    {
        return $this->decoratedPharInfo->getNormalizedMetadata();
    }

    private function getPharHash(): string
    {
        return $this->decoratedPharInfo->getSignature()['hash'] ?? unique_id('');
    }
}
