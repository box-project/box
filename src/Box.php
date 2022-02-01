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

use Amp\MultiReasonException;
use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use function Amp\ParallelFunctions\parallelMap;
use function Amp\Promise\wait;
use function array_filter;
use function array_flip;
use function array_map;
use function array_unshift;
use BadMethodCallException;
use function chdir;
use Closure;
use Countable;
use function dirname;
use function extension_loaded;
use function file_exists;
use function getcwd;
use function is_object;
use KevinGH\Box\Compactor\Compactors;
use KevinGH\Box\Compactor\PhpScoper;
use KevinGH\Box\Compactor\Placeholder;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\file_contents;
use function KevinGH\Box\FileSystem\make_tmp_dir;
use function KevinGH\Box\FileSystem\mkdir;
use function KevinGH\Box\FileSystem\remove;
use KevinGH\Box\PhpScoper\NullScoper;
use KevinGH\Box\PhpScoper\WhitelistManipulator;
use function openssl_pkey_export;
use function openssl_pkey_get_details;
use function openssl_pkey_get_private;
use Phar;
use RecursiveDirectoryIterator;
use RuntimeException;
use SplFileInfo;
use function sprintf;
use Webmozart\Assert\Assert;

/**
 * Box is a utility class to generate a PHAR.
 *
 * @private
 */
final class Box implements Countable
{
    /** @var string The path to the PHAR file */
    private $file;

    /** @var Phar The PHAR instance */
    private $phar;

    private $compactors;
    private $placeholderCompactor;
    private $mapFile;
    private $scoper;
    private $buffering = false;
    private $bufferedFiles = [];

    private function __construct(Phar $phar, string $file)
    {
        $this->phar = $phar;
        $this->file = $file;

        $this->compactors = new Compactors();
        $this->placeholderCompactor = new Placeholder([]);
        $this->mapFile = new MapFile(getcwd(), []);
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
    public static function create(string $file, ?int $flags = null, ?string $alias = null): self
    {
        // Ensure the parent directory of the PHAR file exists as `new \Phar()` does not create it and would fail
        // otherwise.
        mkdir(dirname($file));

        return new self(new Phar($file, (int) $flags, $alias), $file);
    }

    public function startBuffering(): void
    {
        Assert::false($this->buffering, 'The buffering must be ended before starting it again');

        $this->buffering = true;

        $this->phar->startBuffering();
    }

    public function endBuffering(?Closure $dumpAutoload): void
    {
        Assert::true($this->buffering, 'The buffering must be started before ending it');

        $cwd = getcwd();

        $tmp = make_tmp_dir('box', self::class);
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

            if (null !== $dumpAutoload) {
                $dumpAutoload(
                    $this->scoper->getSymbolsRegistry(),
                    $this->scoper->getPrefix()
                );
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
        Assert::false($this->buffering, 'The buffering must have ended before removing the Composer artefacts');

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
        Assert::false($this->buffering, 'Cannot compress files while buffering.');
        Assert::inArray($compressionAlgorithm, get_phar_compression_algorithms());

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
            file_contents($file)
        );

        $this->phar->setStub($contents);
    }

    /**
     * @param SplFileInfo[]|string[] $files
     *
     * @throws MultiReasonException
     */
    public function addFiles(array $files, bool $binary): void
    {
        Assert::true($this->buffering, 'Cannot add files if the buffering has not started.');

        $files = array_map('strval', $files);

        if ($binary) {
            foreach ($files as $file) {
                $this->addFile($file, null, $binary);
            }

            return;
        }

        foreach ($this->processContents($files) as [$file, $contents]) {
            $this->bufferedFiles[$file] = $contents;
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
            $contents = file_contents($file);
        }

        $local = ($this->mapFile)($file);

        $this->bufferedFiles[$local] = $binary ? $contents : $this->compactors->compact($local, $contents);

        return $local;
    }

    public function getPhar(): Phar
    {
        return $this->phar;
    }

    /**
     * Signs the PHAR using a private key file.
     *
     * @param string      $file     the private key file name
     * @param null|string $password the private key password
     */
    public function signUsingFile(string $file, ?string $password = null): void
    {
        $this->sign(file_contents($file), $password);
    }

    /**
     * Signs the PHAR using a private key.
     *
     * @param string      $key      The private key
     * @param null|string $password The private key password
     */
    public function sign(string $key, ?string $password): void
    {
        $pubKey = $this->file.'.pubkey';

        Assert::writable(dirname($pubKey));
        Assert::true(extension_loaded('openssl'));

        if (file_exists($pubKey)) {
            Assert::file(
                $pubKey,
                'Cannot create public key: %s already exists and is not a file.'
            );
        }

        $resource = openssl_pkey_get_private($key, (string) $password);

        Assert::notSame(false, $resource, 'Could not retrieve the private key, check that the password is correct.');

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
        $mapFile = $this->mapFile;
        $compactors = $this->compactors;
        $cwd = getcwd();

        $processFile = static function (string $file) use ($cwd, $mapFile, $compactors): array {
            chdir($cwd);

            // Keep the fully qualified call here since this function may be executed without the right autoloading
            // mechanism
            \KevinGH\Box\register_aliases();
            if (true === \KevinGH\Box\is_parallel_processing_enabled()) {
                \KevinGH\Box\register_error_handler();
            }

            $contents = file_contents($file);

            $local = $mapFile($file);

            $processedContents = $compactors->compact($local, $contents);

            return [$local, $processedContents, $compactors->getScoperSymbolsRegistry()];
        };

        if ($this->scoper instanceof NullScoper || false === is_parallel_processing_enabled()) {
            return array_map($processFile, $files);
        }

        // In the case of parallel processing, an issue is caused due to the statefulness nature of the PhpScoper
        // whitelist.
        //
        // Indeed the PhpScoper Whitelist stores the records of whitelisted classes and functions. If nothing is done,
        // then the whitelisted retrieve in the end will here will be "blank" since the updated whitelists are the ones
        // from the workers used for the parallel processing.
        //
        // In order to avoid that, the whitelists will be returned as a result as well in order to be able to merge
        // all the whitelists into one.
        //
        // This process is allowed thanks to the nature of the state of the whitelists: having redundant classes or
        // functions registered can easily be deal with so merging all those different states is actually
        // straightforward.
        $tuples = wait(parallelMap($files, $processFile));

        if ([] === $tuples) {
            return [];
        }

        $filesWithContents = [];
        $symbolRegistries = [];

        foreach ($tuples as [$local, $processedContents, $symbolRegistry]) {
            $filesWithContents[] = [$local, $processedContents];
            $symbolRegistries[] = $symbolRegistry;
        }

        $this->compactors->registerSymbolsRegistry(
            SymbolsRegistry::createFromRegistries(array_filter($symbolRegistries)),
        );

        return $filesWithContents;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        Assert::false($this->buffering, 'Cannot count the number of files in the PHAR when buffering');

        return $this->phar->count();
    }
}
