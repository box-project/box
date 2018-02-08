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
use Phar;
use RecursiveDirectoryIterator;
use SplFileInfo;
use function Amp\ParallelFunctions\parallelMap;
use function Amp\Promise\wait;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\file_contents;
use function KevinGH\Box\FileSystem\mkdir;

/**
 * Box is a utility class to generate a PHAR.
 */
final class Box
{
    /**
     * @var Compactor[]
     */
    private $compactors = [];

    /**
     * @var string The path to the PHAR file
     */
    private $file;

    /**
     * @var Phar The PHAR instance
     */
    private $phar;

    /**
     * @var scalar[] The placeholders with their values
     */
    private $placeholders = [];

    /**
     * @var RetrieveRelativeBasePath
     */
    private $retrieveRelativeBasePath;

    /**
     * @var MapFile
     */
    private $mapFile;

    private function __construct(Phar $phar, string $file)
    {
        $this->phar = $phar;
        $this->file = $file;

        $this->retrieveRelativeBasePath = function (string $path) { return $path; };
        $this->mapFile = function (): void { };
    }

    /**
     * Creates a new PHAR and Box instance.
     *
     * @param string $file  The PHAR file name
     * @param int    $flags Flags to pass to the Phar parent class RecursiveDirectoryIterator
     * @param string $alias Alias with which the Phar archive should be referred to in calls to stream functionality
     *
     * @return Box
     *
     * @see RecursiveDirectoryIterator
     */
    public static function create(string $file, int $flags = null, string $alias = null): self
    {
        // Ensure the parent directory of the PHAR file exists as `new \Phar()` does not create it and would fail
        // otherwise.
        mkdir(dirname($file));

        return new self(new Phar($file, (int) $flags, $alias), $file);
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

    public function registerFileMapping(RetrieveRelativeBasePath $retrieveRelativeBasePath, MapFile $fileMapper): void
    {
        $this->retrieveRelativeBasePath = $retrieveRelativeBasePath;
        $this->mapFile = $fileMapper;
    }

    public function registerStub(string $file): void
    {
        $contents = self::replacePlaceholders(
            $this->placeholders,
            file_contents($file)
        );

        $this->phar->setStub($contents);
    }

    /**
     * @param SplFileInfo[]|string[] $files
     * @param bool                   $binary
     */
    public function addFiles(array $files, bool $binary): void
    {
        if ($binary) {
            foreach ($files as $file) {
                $this->addFile((string) $file, null, $binary);
            }

            return;
        }

        $tuples = $this->processContents(
            array_map(
                function ($file): string {
                    return (string) $file;
                },
                $files
            )
        );

        foreach ($tuples as $tuple) {
            list($path, $contents) = $tuple;

            $this->phar->addFromString($path, $contents);
        }
    }

    /**
     * Adds the a file to the PHAR. The contents will first be compacted and have its placeholders
     * replaced.
     *
     * @param string      $file
     * @param null|string $contents If null the content of the file will be used
     * @param bool        $binary   When true means the file content shouldn't be processed
     *
     * @return string File local path
     */
    public function addFile(string $file, string $contents = null, bool $binary = false): string
    {
        Assertion::file($file);
        Assertion::readable($file);

        $contents = null === $contents ? file_get_contents($file) : $contents;

        $relativePath = ($this->retrieveRelativeBasePath)($file);
        $local = ($this->mapFile)($relativePath);

        if (null === $local) {
            $local = $relativePath;
        }

        if ($binary) {
            $this->phar->addFile($file, $local);
        } else {
            $processedContents = self::compactContents(
                $this->compactors,
                $local,
                self::replacePlaceholders($this->placeholders, $contents)
            );

            $this->phar->addFromString($local, $processedContents);
        }

        return $local;
    }

    public function getPhar(): Phar
    {
        return $this->phar;
    }

    /**
     * Signs the PHAR using a private key file.
     *
     * @param string $file     the private key file name
     * @param string $password the private key password
     */
    public function signUsingFile(string $file, string $password = null): void
    {
        $this->sign(file_contents($file), $password);
    }

    /**
     * Signs the PHAR using a private key.
     *
     * @param string $key      The private key
     * @param string $password The private key password
     */
    public function sign(string $key, ?string $password): void
    {
        $pubKey = $this->file.'.pubkey';

        Assertion::writeable(dirname($pubKey));
        Assertion::extensionLoaded('openssl');

        if (file_exists($pubKey)) {
            Assertion::file(
                $pubKey,
                'Cannot create public key: "%s" already exists and is not a file.'
            );
        }

        $resource = openssl_pkey_get_private($key, (string) $password);

        openssl_pkey_export($resource, $private);

        $details = openssl_pkey_get_details($resource);

        $this->phar->setSignatureAlgorithm(Phar::OPENSSL, $private);

        dump_file($pubKey, $details['key']);
    }

    /**
     * @param string[]
     *
     * @return array
     */
    private function processContents(array $files): array
    {
        $retrieveRelativeBasePath = $this->retrieveRelativeBasePath;
        $mapFile = $this->mapFile;
        $placeholders = $this->placeholders;
        $compactors = $this->compactors;

        $processFile = function (string $file) use ($retrieveRelativeBasePath, $mapFile, $placeholders, $compactors): array {
            $contents = file_contents($file);

            $relativePath = $retrieveRelativeBasePath($file);
            $local = $mapFile($relativePath);

            if (null === $local) {
                $local = $relativePath;
            }

            $processedContents = self::compactContents(
                $compactors,
                $local,
                self::replacePlaceholders($placeholders, $contents)
            );

            return [$local, $processedContents];
        };

        return wait(parallelMap($files, $processFile));
    }

    /**
     * Replaces the placeholders with their values.
     *
     * @param array  $placeholders
     * @param string $contents     the contents
     *
     * @return string the replaced contents
     */
    private static function replacePlaceholders(array $placeholders, string $contents): string
    {
        return str_replace(
            array_keys($placeholders),
            array_values($placeholders),
            $contents
        );
    }

    private static function compactContents(array $compactors, string $file, string $contents): string
    {
        return array_reduce(
            $compactors,
            function (string $contents, Compactor $compactor) use ($file): string {
                return $compactor->compact($file, $contents);
            },
            $contents
        );
    }
}
