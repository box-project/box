<?php

declare(strict_types=1);

namespace KevinGH\Box\PhpScoper;

use Closure;
use function func_get_args;
use Humbug\PhpScoper\Scoper as HumbugPhpScoperScoper;
use Humbug\PhpScoper\Throwable\Exception\ParsingException;
use Humbug\PhpScoper\Whitelist;
use Opis\Closure\SerializableClosure;
use Serializable;
use function serialize;
use function unserialize;

/**
 * Humbug PHP-Scoper scoper which leverages closures to ensure the scoper is serialiable.
 */
final class SerializablePhpScoper implements HumbugPhpScoperScoper, Serializable
{
    private $createScoper;
    private $scoper;

    public function __construct(Closure $createScoper)
    {
        $this->createScoper = new SerializableClosure($createScoper);

        // Checks that the scoper is correctly instantiable instead of lazily checking it
        $this->getScoper();
    }

    /**
     * {@inheritdoc}
     */
    public function scope(string $filePath, string $contents, string $prefix, array $patchers, Whitelist $whitelist): string
    {
        return $this->getScoper()->scope(...func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(): string
    {
        return serialize($this->createScoper);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized): void
    {
        $this->createScoper = unserialize($serialized);
    }

    public function getScoper(): HumbugPhpScoperScoper
    {
        if (null === $this->scoper) {
            $this->scoper = ($this->createScoper)();
        }

        return $this->scoper;
    }
}