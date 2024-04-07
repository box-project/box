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

use Amp\Parallel\Worker\TaskFailureThrowable;
use BadMethodCallException;
use Countable;
use DateTimeImmutable;
use Fidry\FileSystem\FS;
use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use KevinGH\Box\Compactor\Compactors;
use KevinGH\Box\Compactor\PhpScoper;
use KevinGH\Box\Compactor\Placeholder;
use KevinGH\Box\Filesystem\LocalPharFile;
use KevinGH\Box\Parallelization\ParallelFileProcessor;
use KevinGH\Box\Parallelization\ParallelizationDecider;
use KevinGH\Box\Phar\CompressionAlgorithm;
use KevinGH\Box\Phar\SigningAlgorithm;
use KevinGH\Box\PhpScoper\NullScoper;
use KevinGH\Box\PhpScoper\Scoper;
use Phar;
use RecursiveDirectoryIterator;
use RuntimeException;
use Seld\PharUtils\Timestamps;
use SplFileInfo;
use Webmozart\Assert\Assert;
use function array_map;
use function array_unshift;
use function chdir;
use function dirname;
use function extension_loaded;
use function file_exists;
use function getcwd;
use function is_object;
use function openssl_pkey_export;
use function openssl_pkey_get_details;
use function openssl_pkey_get_private;
use function sprintf;
use function strval;

/**
 * Box is a utility class to generate a PHAR.
 *
 * @private
 */
final class Box implements Countable
{
    private Compactors $compactors;
    private Placeholder $placeholderCompactor;
    private MapFile $mapFile;
    private Scoper $scoper;
    private bool $buffering = false;

    /**
     * @var array<string, LocalPharFile>
     */
    private array $bufferedFiles = [];

    private function __construct(
        private Phar $phar,
        private readonly string $pharFilePath,
        private readonly bool $enableParallelization,
    ) {
        $this->compactors = new Compactors();
        $this->placeholderCompactor = new Placeholder([]);
        $this->mapFile = new MapFile(getcwd(), []);
        $this->scoper = new NullScoper();
    }

    /**
     * Creates a new PHAR and Box instance.
     *
     * @param string $pharFilePath The PHAR file name
     * @param int    $pharFlags    Flags to pass to the Phar parent class RecursiveDirectoryIterator
     * @param string $pharAlias    Alias with which the Phar archive should be referred to in calls to stream functionality
     *
     * @see RecursiveDirectoryIterator
     */
    public static function create(
        string $pharFilePath,
        int $pharFlags = 0,
        ?string $pharAlias = null,
        bool $enableParallelization = false,
    ): self {
        // Ensure the parent directory of the PHAR file exists as `new \Phar()` does not create it and would fail
        // otherwise.
        FS::mkdir(dirname($pharFilePath));

        return new self(
            new Phar($pharFilePath, $pharFlags, $pharAlias),
            $pharFilePath,
            $enableParallelization,
        );
    }

    public function startBuffering(): void
    {
        Assert::false($this->buffering, 'The buffering must be ended before starting it again');

        $this->buffering = true;

        $this->phar->startBuffering();
    }

    /**
     * @param callable(SymbolsRegistry, string, string[]): void $dumpAutoload
     */
    public function endBuffering(?callable $dumpAutoload): void
    {
        Assert::true($this->buffering, 'The buffering must be started before ending it');

        $dumpAutoload ??= static fn () => null;
        $cwd = getcwd();

        $tmp = FS::makeTmpDir('box', self::class);
        chdir($tmp);

        if ([] === $this->bufferedFiles) {
            $this->bufferedFiles = [
                new LocalPharFile(
                    '.box_empty',
                    'A PHAR cannot be empty so Box adds this file to ensure the PHAR is created still.',
                ),
            ];
        }

        try {
            foreach ($this->bufferedFiles as $file) {
                FS::dumpFile(
                    $file->getPath(),
                    $file->getContents(),
                );
            }

            if (null !== $dumpAutoload) {
                $dumpAutoload(
                    $this->scoper->getSymbolsRegistry(),
                    $this->scoper->getPrefix(),
                    $this->scoper->getExcludedFilePaths(),
                );
            }

            chdir($cwd);

            $this->phar->buildFromDirectory($tmp);
        } finally {
            FS::remove($tmp);
        }

        $this->buffering = false;

        $this->phar->stopBuffering();
    }

    /**
     * @param non-empty-string $normalizedVendorDir Normalized path ("/" path separator and no trailing "/") to the Composer vendor directory
     */
    public function removeComposerArtifacts(string $normalizedVendorDir): void
    {
        Assert::false($this->buffering, 'The buffering must have ended before removing the Composer artefacts');

        $composerArtifacts = [
            'composer.json',
            'composer.lock',
            $normalizedVendorDir.'/composer/installed.json',
        ];

        $this->phar->startBuffering();

        foreach ($composerArtifacts as $composerArtifact) {
            $localComposerArtifact = ($this->mapFile)($composerArtifact);

            $pharFilePath = sprintf(
                'phar://%s/%s',
                $this->phar->getPath(),
                $localComposerArtifact,
            );

            if (file_exists($pharFilePath)) {
                $this->phar->delete($localComposerArtifact);
            }
        }

        $this->phar->stopBuffering();
    }

    public function compress(CompressionAlgorithm $compressionAlgorithm): ?string
    {
        Assert::false($this->buffering, 'Cannot compress files while buffering.');

        $extensionRequired = $compressionAlgorithm->getRequiredExtension();

        if (null !== $extensionRequired && false === extension_loaded($extensionRequired)) {
            throw new RuntimeException(
                sprintf(
                    'Cannot compress the PHAR with the compression algorithm "%s": the extension "%s" is required but appear to not be loaded',
                    $compressionAlgorithm->name,
                    $extensionRequired,
                ),
            );
        }

        try {
            if (CompressionAlgorithm::NONE === $compressionAlgorithm) {
                $this->phar->decompressFiles();
            } else {
                $this->phar->compressFiles($compressionAlgorithm->value);
            }
        } catch (BadMethodCallException $exception) {
            $exceptionMessage = 'unable to create temporary file' !== $exception->getMessage()
                ? 'Could not compress the PHAR: '.$exception->getMessage()
                : sprintf(
                    'Could not compress the PHAR: the compression requires too many file descriptors to be opened (%s). Check your system limits or install the posix extension to allow Box to automatically configure it during the compression',
                    $this->phar->count(),
                );

            throw new RuntimeException($exceptionMessage, $exception->getCode(), $exception);
        }

        return $extensionRequired;
    }

    public function registerCompactors(Compactors $compactors): void
    {
        $compactorsArray = $compactors->toArray();

        foreach ($compactorsArray as $index => $compactor) {
            if ($compactor instanceof PhpScoper) {
                $this->scoper = $compactor->getScoper();

                continue;
            }

            if ($compactor instanceof Placeholder) {
                // Removes the known Placeholder compactors in favour of the Box one
                unset($compactorsArray[$index]);
            }
        }

        array_unshift($compactorsArray, $this->placeholderCompactor);

        $this->compactors = new Compactors(...$compactorsArray);
    }

    /**
     * @param scalar[] $placeholders
     */
    public function registerPlaceholders(array $placeholders): void
    {
        $message = 'Expected value "%s" to be a scalar or stringable object.';

        foreach ($placeholders as $index => $placeholder) {
            if (is_object($placeholder)) {
                Assert::methodExists($placeholder, '__toString', $message);

                $placeholders[$index] = (string) $placeholder;

                break;
            }

            Assert::scalar($placeholder, $message);
        }

        $this->placeholderCompactor = new Placeholder($placeholders);

        $this->registerCompactors($this->compactors);
    }

    public function registerFileMapping(MapFile $fileMapper): void
    {
        $this->mapFile = $fileMapper;
    }

    public function registerStub(string $file): void
    {
        $contents = $this->placeholderCompactor->compact(
            $file,
            FS::getFileContents($file),
        );

        $this->phar->setStub($contents);
    }

    /**
     * @param array<SplFileInfo|string> $files
     *
     * @throws TaskFailureThrowable
     */
    public function addFiles(array $files, bool $binary): void
    {
        Assert::true($this->buffering, 'Cannot add files if the buffering has not started.');

        $files = array_map(strval(...), $files);

        if ($binary) {
            foreach ($files as $file) {
                $this->addFile($file, null, true);
            }

            return;
        }

        foreach ($this->processContents($files) as $file) {
            $this->bufferedFiles[$file->getPath()] = $file;
        }
    }

    /**
     * Adds the a file to the PHAR. The contents will first be compacted and have its placeholders
     * replaced.
     *
     * @param null|string $contents If null the content of the file will be used
     * @param bool        $binary   When true means the file content shouldn't be processed
     *
     * @return string File local path
     */
    public function addFile(string $file, ?string $contents = null, bool $binary = false): string
    {
        Assert::true($this->buffering, 'Cannot add files if the buffering has not started.');

        if (null === $contents) {
            $contents = FS::getFileContents($file);
        }

        $local = ($this->mapFile)($file);

        $this->bufferedFiles[$local] = new LocalPharFile(
            $local,
            $binary ?
                $contents :
                $this->compactors->compact($local, $contents),
        );

        return $local;
    }

    /**
     * @internal
     */
    public function getPhar(): Phar
    {
        return $this->phar;
    }

    public function setAlias(string $alias): void
    {
        $aliasWasAdded = $this->phar->setAlias($alias);

        Assert::true(
            $aliasWasAdded,
            sprintf(
                'The alias "%s" is invalid. See Phar::setAlias() documentation for more information.',
                $alias,
            ),
        );
    }

    public function setStub(string $stub): void
    {
        $this->phar->setStub($stub);
    }

    public function setDefaultStub(string $main): void
    {
        $this->phar->setDefaultStub($main);
    }

    public function setMetadata(mixed $metadata): void
    {
        $this->phar->setMetadata($metadata);
    }

    public function extractTo(string $directory, bool $overwrite = false): void
    {
        $this->phar->extractTo($directory, overwrite: $overwrite);
    }

    public function sign(
        SigningAlgorithm $signingAlgorithm,
        ?DateTimeImmutable $timestamp = null,
    ): void {
        if (null === $timestamp) {
            $this->phar->setSignatureAlgorithm($signingAlgorithm->value);

            return;
        }

        $phar = $this->phar;
        $phar->__destruct();
        unset($this->phar);

        $util = new Timestamps($this->pharFilePath);
        $util->updateTimestamps($timestamp);
        $util->save(
            $this->pharFilePath,
            $signingAlgorithm->value,
        );

        $this->phar = new Phar($this->pharFilePath);
    }

    /**
     * Signs the PHAR using a private key file.
     *
     * @param string      $file     the private key file name
     * @param null|string $password the private key password
     */
    public function signUsingFile(string $file, ?string $password = null): void
    {
        $this->signUsingKey(FS::getFileContents($file), $password);
    }

    /**
     * Signs the PHAR using a private key.
     *
     * @param string      $key      The private key
     * @param null|string $password The private key password
     */
    public function signUsingKey(string $key, ?string $password): void
    {
        $pubKey = $this->pharFilePath.'.pubkey';

        Assert::writable(dirname($pubKey));
        Assert::true(extension_loaded('openssl'));

        if (file_exists($pubKey)) {
            Assert::file(
                $pubKey,
                'Cannot create public key: %s already exists and is not a file.',
            );
        }

        $resource = openssl_pkey_get_private($key, (string) $password);

        Assert::notSame(false, $resource, 'Could not retrieve the private key, check that the password is correct.');

        openssl_pkey_export($resource, $private);

        $details = openssl_pkey_get_details($resource);

        $this->phar->setSignatureAlgorithm(Phar::OPENSSL, $private);

        FS::dumpFile($pubKey, $details['key']);
    }

    /**
     * @param string[] $files
     *
     * @return LocalPharFile[]
     */
    private function processContents(array $files): array
    {
        $cwd = getcwd();

        $shouldProcessFilesInParallel = $this->enableParallelization && ParallelizationDecider::shouldProcessFilesInParallel(
            $this->scoper,
            count($files),
        );

        $processFiles = $shouldProcessFilesInParallel
            ? ParallelFileProcessor::processFilesInParallel(...)
            : self::processFilesSynchronously(...);

        return $processFiles(
            $files,
            $cwd,
            $this->mapFile,
            $this->compactors,
        );
    }

    /**
     * @param string[] $files
     *
     * @return LocalPharFile[]
     */
    private static function processFilesSynchronously(
        array $files,
        string $_cwd,
        MapFile $mapFile,
        Compactors $compactors,
    ): array {
        $processFile = static function (string $file) use ($mapFile, $compactors): LocalPharFile {
            $contents = FS::getFileContents($file);

            $local = $mapFile($file);

            $processedContents = $compactors->compact($local, $contents);

            return new LocalPharFile(
                $local,
                $processedContents,
            );
        };

        return array_map($processFile, $files);
    }

    public function count(): int
    {
        Assert::false($this->buffering, 'Cannot count the number of files in the PHAR when buffering');

        return $this->phar->count();
    }
}
