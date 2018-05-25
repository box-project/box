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
use BadMethodCallException;
use Countable;
use KevinGH\Box\Compactor\PhpScoper;
use KevinGH\Box\Composer\ComposerOrchestrator;
use KevinGH\Box\PhpScoper\NullScoper;
use KevinGH\Box\PhpScoper\Scoper;
use Phar;
use RecursiveDirectoryIterator;
use RuntimeException;
use SplFileInfo;
use function Amp\ParallelFunctions\parallelMap;
use function Amp\Promise\wait;
use function array_flip;
use function array_map;
use function chdir;
use function extension_loaded;
use function file_exists;
use function getcwd;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\file_contents;
use function KevinGH\Box\FileSystem\make_path_relative;
use function KevinGH\Box\FileSystem\make_tmp_dir;
use function KevinGH\Box\FileSystem\mkdir;
use function KevinGH\Box\FileSystem\remove;
use function sprintf;

/**
 * Box is a utility class to generate a PHAR.
 *
 * @private
 */
final class Box implements Countable
{
    public const DEBUG_DIR = '.box_dump';

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
     * @var string
     */
    private $basePath;

    /**
     * @var MapFile
     */
    private $mapFile;

    /**
     * @var Scoper
     */
    private $scoper;

    private $buffering = false;

    private $bufferedFiles = [];

    private function __construct(Phar $phar, string $file)
    {
        $this->phar = $phar;
        $this->file = $file;

        $this->basePath = getcwd();
        $this->mapFile = new MapFile([]);
        $this->scoper = new NullScoper();
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

    public function startBuffering(): void
    {
        Assertion::false($this->buffering, 'The buffering must be ended before starting it again');

        $this->buffering = true;

        $this->phar->startBuffering();
    }

    public function endBuffering(bool $dumpAutoload): void
    {
        Assertion::true($this->buffering, 'The buffering must be started before ending it');

        $cwd = getcwd();

        $tmp = make_tmp_dir('box', __CLASS__);
        chdir($tmp);

        if ([] === $this->bufferedFiles) {
            $this->bufferedFiles = [
                '.box_empty' => 'A PHAR cannot be empty so Box adds this file to ensure the PHAR is created still.',
            ];
        }

        try {
            foreach ($this->bufferedFiles as $file => $contents) {
                dump_file($file, $contents);
            }

            if ($dumpAutoload) {
                // Dump autoload without dev dependencies
                ComposerOrchestrator::dumpAutoload($this->scoper->getWhitelist(), $this->scoper->getPrefix());
            }

            chdir($cwd);

            $this->phar->buildFromDirectory($tmp);
        } finally {
            remove($tmp);
        }

        $this->buffering = false;

        $this->phar->stopBuffering();
    }

    public function removeComposerArtefacts(string $vendorDir): void
    {
        Assertion::false($this->buffering, 'The buffering must have ended before removing the Composer artefacts');

        $composerFiles = [
            'composer.json',
            'composer.lock',
            $vendorDir.'/composer/installed.json',
        ];

        $this->phar->startBuffering();

        foreach ($composerFiles as $composerFile) {
            $localComposerFile = ($this->mapFile)($composerFile);

            if (file_exists('phar://'.$this->phar->getPath().'/'.$localComposerFile)) {
                $this->phar->delete($localComposerFile);
            }
        }

        $this->phar->stopBuffering();
    }

    /**
     * @return null|string The required extension to execute the PHAR now that it is compressed
     */
    public function compress(int $compressionAlgorithm): ?string
    {
        Assertion::false($this->buffering, 'Cannot compress files while buffering.');
        Assertion::inArray($compressionAlgorithm, get_phar_compression_algorithms());

        $extensionRequired = get_phar_compression_algorithm_extension($compressionAlgorithm);

        if (null !== $extensionRequired && false === extension_loaded($extensionRequired)) {
            throw new RuntimeException(
                sprintf(
                    'Cannot compress the PHAR with the compression algorithm "%s": the extension "%s" is required but appear to not '
                    .'be loaded',
                    array_flip(get_phar_compression_algorithms())[$compressionAlgorithm],
                    $extensionRequired
                )
            );
        }

        try {
            if (Phar::NONE === $compressionAlgorithm) {
                $this->getPhar()->decompressFiles();
            } else {
                $this->phar->compressFiles($compressionAlgorithm);
            }
        } catch (BadMethodCallException $exception) {
            $exceptionMessage = 'unable to create temporary file' !== $exception->getMessage()
                ? 'Could not compress the PHAR: '.$exception->getMessage()
                : sprintf(
                    'Could not compress the PHAR: the compression requires too many file descriptors to be opened (%s). Check '
                    .'your system limits or install the posix extension to allow Box to automatically configure it during the compression',
                    $this->phar->count()
                )
            ;

            throw new RuntimeException($exceptionMessage, $exception->getCode(), $exception);
        }

        return $extensionRequired;
    }

    /**
     * @param Compactor[] $compactors
     */
    public function registerCompactors(array $compactors): void
    {
        Assertion::allIsInstanceOf($compactors, Compactor::class);

        $this->compactors = $compactors;

        foreach ($this->compactors as $compactor) {
            if ($compactor instanceof PhpScoper) {
                $this->scoper = $compactor->getScoper();

                break;
            }
        }
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

    public function registerFileMapping(string $basePath, MapFile $fileMapper): void
    {
        $this->basePath = $basePath;
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
     */
    public function addFiles(array $files, bool $binary): void
    {
        Assertion::true($this->buffering, 'Cannot add files if the buffering has not started.');

        $files = array_map(
            function ($file): string {
                // Convert files to string as SplFileInfo is not serializable
                return (string) $file;
            },
            $files
        );

        if ($binary) {
            foreach ($files as $file) {
                $this->addFile($file, null, $binary);
            }

            return;
        }

        $filesWithContents = $this->processContents($files);

        foreach ($filesWithContents as $fileWithContents) {
            [$file, $contents] = $fileWithContents;

            $this->bufferedFiles[$file] = $contents;
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
        Assertion::true($this->buffering, 'Cannot add files if the buffering has not started.');

        if (null === $contents) {
            $contents = file_contents($file);
        }

        $relativePath = make_path_relative($file, $this->basePath);
        $local = ($this->mapFile)($relativePath);

        if ($binary) {
            $this->bufferedFiles[$local] = $contents;
        } else {
            $processedContents = self::compactContents(
                $this->compactors,
                $local,
                self::replacePlaceholders($this->placeholders, $contents)
            );

            $this->bufferedFiles[$local] = $processedContents;
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
     * @param string[] $files
     *
     * @return array array of tuples where the first element is the local file path (path inside the PHAR) and the
     *               second element is the processed contents
     */
    private function processContents(array $files): array
    {
        $basePath = $this->basePath;
        $mapFile = $this->mapFile;
        $placeholders = $this->placeholders;
        $compactors = $this->compactors;
        $bootstrap = $GLOBALS['_BOX_BOOTSTRAP'] ?? function (): void {};
        $cwd = getcwd();

        $processFile = function (string $file) use ($cwd, $basePath, $mapFile, $placeholders, $compactors, $bootstrap): array {
            chdir($cwd);
            $bootstrap();

            $contents = file_contents($file);

            $relativePath = make_path_relative($file, $basePath);
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

        return is_parallel_processing_enabled() && false === ($this->scoper instanceof NullScoper)
            ? wait(parallelMap($files, $processFile))
            : array_map($processFile, $files)
        ;
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

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        Assertion::false($this->buffering, 'Cannot count the number of files in the PHAR when buffering');

        return $this->phar->count();
    }
}
