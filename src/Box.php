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

use function array_reduce;
use Assert\Assert;
use Assert\Assertion;
use function file_get_contents;
use FilesystemIterator;
use function is_object;
use KevinGH\Box\Compactor;
use KevinGH\Box\Exception\FileExceptionFactory;
use KevinGH\Box\Exception\InvalidArgumentException;
use KevinGH\Box\Exception\OpenSslExceptionFactory;
use KevinGH\Box\Exception\UnexpectedValueException;
use Phar;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use SplFileInfo;
use SplObjectStorage;
use Traversable;

final class Box
{
    /**
     * The source code compactors.
     *
     * @var Compactor[]
     */
    private $compactors = [];

    /**
     * The path to the Phar file.
     *
     * @var string
     */
    private $file;

    /**
     * The Phar instance.
     *
     * @var Phar
     */
    private $phar;

    /**
     * The placeholder values.
     *
     * @var array
     */
    private $placeholders = [];

    /**
     * Creates a new Phar and Box instance.
     *
     * @param string $file  the file name
     * @param int    $flags Flags to pass to the Phar parent class RecursiveDirectoryIterator
     * @param string $alias Alias with which the Phar archive should be referred to in calls to stream functionality
     *
     * @return Box
     *
     * @see RecursiveDirectoryIterator
     */
    public static function create(string $file, int $flags = null, string $alias = null): self
    {
        return new self(new Phar($file, (int) $flags, $alias), $file);
    }

    private function __construct(Phar $phar, string $file)
    {
        $this->phar = $phar;
        $this->file = $file;
    }

    /**
     * @param Compactor[] $compactors
     */
    public function registerCompactors(array $compactors): void
    {
        Assertion::allIsInstanceOf($compactors, Compactor::class);

        $this->compactors = $compactors;
    }

    /**
     * Sets the placeholder values.
     *
     * @param scalar[] $placeholders
     */
    public function registerPlaceholders(array $placeholders): void
    {
        $message = 'Expected value "%s" to be a scalar or stringable object.';

        foreach ($placeholders as $i => $placeholder) {
            if (is_object($placeholder)) {
                Assertion::methodExists('__toString', $placeholder, $message);

                $placeholders[$i] = (string) $placeholder;

                break;
            }

            Assertion::scalar($placeholder, $message);
        }

        $this->placeholders = $placeholders;
    }

    /**
     * Adds the a file to the PHAR. The contents will first be compacted and have its placeholders
     * replaced.
     *
     * @param string $file  The file name or path
     * @param string $local The local file name or path
     */
    public function addFile($file, $local = null): void
    {
        if (null === $local) {
            $local = $file;
        }

        Assertion::file($file);
        Assertion::readable($file);

        $contents = file_get_contents($file);

        $this->addFromString($local, $contents);
    }

    /**
     * Adds the contents from a file to the PHAR. The contents will first be compacted and have its placeholders
     * replaced.
     *
     * @param string $local    The local name or path
     * @param string $contents The contents
     */
    public function addFromString(string $local, string $contents): void
    {
        $this->phar->addFromString(
            $local,
            $this->compactContents(
                $local,
                $this->replacePlaceholders($contents)
            )
        );
    }

    public function getPhar(): Phar
    {
        return $this->phar;
    }

    /**
     * Sets the bootstrap loader stub using a file.
     *
     * @param string $file    the file path
     * @param bool   $replace Replace placeholders?
     *
     * @throws Exception\Exception
     * @throws FileExceptionFactory       if the stub file could not be used
     */
    public function setStubUsingFile($file, $replace = false): void
    {
        Assertion::file($file);
        Assertion::readable($file);

        $contents = file_get_contents($file);

        if ($replace) {
            $contents = $this->replacePlaceholders($contents);
        }

        $this->phar->setStub($contents);
    }

    /**
     * Signs the PHAR using a private key file.
     *
     * @param string $file     the private key file name
     * @param string $password the private key password
     */
    public function signUsingFile(string $file, string $password = null): void
    {
        Assertion::file($file);
        Assertion::readable($file);

        $this->sign(file_get_contents($file), $password);
    }

    /**
     * Signs the PHAR using a private key.
     *
     * @param string $key      The private key
     * @param string $password The private key password
     */
    public function sign(string $key, ?string $password): void
    {
        OpenSslExceptionFactory::reset();

        $pubKey = $this->file.'.pubkey';

        Assertion::extensionLoaded('openssl');

        if (false === ($resource = openssl_pkey_get_private($key, $password))) {
            throw OpenSslExceptionFactory::createForLastError();
        }

        if (false === openssl_pkey_export($resource, $private)) {
            throw OpenSslExceptionFactory::createForLastError();
        }

        if (false === ($details = openssl_pkey_get_details($resource))) {
            throw OpenSslExceptionFactory::createForLastError();
        }

        $this->phar->setSignatureAlgorithm(Phar::OPENSSL, $private);

        if (false === @file_put_contents($pubKey, $details['key'])) {
            throw FileExceptionFactory::createForLastError();
        }
    }

    /**
     * Replaces the placeholders with their values.
     *
     * @param string $contents the contents
     *
     * @return string the replaced contents
     */
    private function replacePlaceholders(string $contents): string
    {
        return str_replace(
            array_keys($this->placeholders),
            array_values($this->placeholders),
            $contents
        );
    }

    private function compactContents(string $file, string $contents): string
    {
        return array_reduce(
            $this->compactors,
            function (string $contents, Compactor $compactor) use ($file): string {
                return $compactor->compact($file, $contents);
            },
            $contents
        );
    }
}
