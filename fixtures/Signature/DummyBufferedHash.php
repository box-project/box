<?php

declare(strict_types=1);

namespace KevinGH\Box\Signature;

use KevinGH\Box\Exception\SignatureException;

final class DummyBufferedHash extends BufferedHash
{
    /**
     * @inheritdoc
     */
    public function __construct(string $algorithm, string $path)
    {
    }

    /**
     * @inheritdoc
     */
    public function verify(string $signature): bool
    {
    }

    public function getPublicBufferedData(): string
    {
        return $this->getBufferedData();
    }
}