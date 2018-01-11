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
use KevinGH\Box\Compactor;
use KevinGH\Box\Exception\FileException;
use KevinGH\Box\Exception\InvalidArgumentException;
use KevinGH\Box\Exception\OpenSslException;
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
    private $values = [];

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

    //TODO: make private
    public function __construct(Phar $phar, string $file)
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
                $this->replaceValues($contents)
            )
        );
    }

    /**
     * Returns the Phar instance.
     *
     * @return Phar the instance
     */
    public function getPhar()
    {
        return $this->phar;
    }

    /**
     * Returns the signature of the phar.
     *
     * This method does not use the extension to extract the phar's signature.
     *
     * @param string $path the phar file path
     *
     * @return array the signature
     *
     * @internal
     */
    public static function getSignature($path)
    {
        return Signature::create($path)->get();
    }

    /**
     * Replaces the placeholders with their values.
     *
     * @param string $contents the contents
     *
     * @return string the replaced contents
     *
     * @internal
     */
    public function replaceValues($contents)
    {
        return str_replace(
            array_keys($this->values),
            array_values($this->values),
            $contents
        );
    }

    /**
     * Sets the bootstrap loader stub using a file.
     *
     * @param string $file    the file path
     * @param bool   $replace Replace placeholders?
     *
     * @throws Exception\Exception
     * @throws FileException       if the stub file could not be used
     */
    public function setStubUsingFile($file, $replace = false): void
    {
        if (false === is_file($file)) {
            throw FileException::create(
                'The file "%s" does not exist or is not a file.',
                $file
            );
        }

        if (false === ($contents = @file_get_contents($file))) {
            throw FileException::lastError();
        }

        if ($replace) {
            $contents = $this->replaceValues($contents);
        }

        $this->phar->setStub($contents);
    }

    /**
     * Sets the placeholder values.
     *
     * @param array $values the values
     *
     * @throws Exception\Exception
     * @throws InvalidArgumentException if a non-scalar value is used
     */
    public function setValues(array $values): void
    {
        foreach ($values as $value) {
            if (false === is_scalar($value)) {
                throw InvalidArgumentException::create(
                    'Non-scalar values (such as %s) are not supported.',
                    gettype($value)
                );
            }
        }

        $this->values = $values;
    }

    /**
     * Signs the Phar using a private key.
     *
     * @param string $key      the private key
     * @param string $password the private key password
     *
     * @throws Exception\Exception
     * @throws OpenSslException    if the "openssl" extension could not be used
     *                             or has generated an error
     *
     * @internal
     */
    public function sign($key, $password = null): void
    {
        OpenSslException::reset();

        if (false === extension_loaded('openssl')) {
            // @codeCoverageIgnoreStart
            throw OpenSslException::create(
                'The "openssl" extension is not available.'
            );
            // @codeCoverageIgnoreEnd
        }

        if (false === ($resource = openssl_pkey_get_private($key, $password))) {
            // @codeCoverageIgnoreStart
            throw OpenSslException::lastError();
            // @codeCoverageIgnoreEnd
        }

        if (false === openssl_pkey_export($resource, $private)) {
            // @codeCoverageIgnoreStart
            throw OpenSslException::lastError();
            // @codeCoverageIgnoreEnd
        }

        if (false === ($details = openssl_pkey_get_details($resource))) {
            // @codeCoverageIgnoreStart
            throw OpenSslException::lastError();
            // @codeCoverageIgnoreEnd
        }

        $this->phar->setSignatureAlgorithm(Phar::OPENSSL, $private);

        if (false === @file_put_contents($this->file.'.pubkey', $details['key'])) {
            throw FileException::lastError();
        }
    }

    /**
     * Signs the Phar using a private key file.
     *
     * @param string $file     the private key file name
     * @param string $password the private key password
     *
     * @throws Exception\Exception
     * @throws FileException       if the private key file could not be read
     */
    public function signUsingFile($file, $password = null): void
    {
        if (false === is_file($file)) {
            throw FileException::create(
                'The file "%s" does not exist or is not a file.',
                $file
            );
        }

        if (false === ($key = @file_get_contents($file))) {
            throw FileException::lastError();
        }

        $this->sign($key, $password);
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
