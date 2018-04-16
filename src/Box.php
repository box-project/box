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
use Humbug\PhpScoper\Console\Configuration as PhpScoperConfiguration;
use KevinGH\Box\Compactor\PhpScoper;
use KevinGH\Box\Composer\ComposerOrchestrator;
use Phar;
use RecursiveDirectoryIterator;
use SplFileInfo;
use const DIRECTORY_SEPARATOR;
use function Amp\ParallelFunctions\parallelMap;
use function Amp\Promise\wait;
use function array_map;
use function chdir;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\file_contents;
use function KevinGH\Box\FileSystem\make_path_relative;
use function KevinGH\Box\FileSystem\make_tmp_dir;
use function KevinGH\Box\FileSystem\mkdir;
use function KevinGH\Box\FileSystem\remove;

/**
 * Box is a utility class to generate a PHAR.
 *
 * @private
 */
final class Box
{
    public const DEBUG_DIR = '.box';

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
     * @var null|PhpScoperConfiguration
     */
    private $phpScoperConfig;

    private function __construct(Phar $phar, string $file)
    {
        $this->phar = $phar;
        $this->file = $file;

        $this->basePath = getcwd();
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

        foreach ($this->compactors as $compactor) {
            if ($compactor instanceof PhpScoper) {
                $this->phpScoperConfig = $compactor->getConfiguration();

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
     * @param bool                   $binary
     */
    public function addFiles(array $files, bool $binary): void
    {
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

        $cwd = getcwd();

        $filesWithContents = $this->processContents($files, $cwd);

        $tmp = make_tmp_dir('box', __CLASS__);
        chdir($tmp);

        try {
            foreach ($filesWithContents as $fileWithContents) {
                [$file, $contents] = $fileWithContents;

                dump_file($file, $contents);

                if (is_debug_enabled()) {
                    dump_file($cwd.'/'.self::DEBUG_DIR.'/'.$file, $contents);
                }
            }

            // Dump autoload without dev dependencies
            ComposerOrchestrator::dumpAutoload($this->phpScoperConfig);

            chdir($cwd);

            $this->phar->buildFromDirectory($tmp);
        } finally {
            remove($tmp);
        }
    }

    /**
     * Adds the a file to the PHAR. The contents will first be compacted and have its placeholders
     * replaced.
     *
     * @param string      $file
     * @param null|string $contents If null the content of the file will be used
     * @param bool        $binary   When true means the file content shouldn't be processed
     * @param string      $root
     *
     * @return string File local path
     */
    public function addFile(string $file, string $contents = null, bool $binary = false): string
    {
        if (null === $contents) {
            $contents = file_contents($file);
        }

        $relativePath = make_path_relative($file, $this->basePath);
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

        if (is_debug_enabled()) {
            if (false === isset($processedContents)) {
                $processedContents = $contents;
            }

            dump_file(self::DEBUG_DIR.DIRECTORY_SEPARATOR.$relativePath, $processedContents);
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
     * @param string   $cwd   Current working directory. As the processes are spawned for parallel processing, the
     *                        working directory may change so we pass the working directory in which the processing
     *                        is supposed to happen. This should not happen during regular usage as all the files are
     *                        absolute but it's possible this class is used with relative paths in which case this is
     *                        an issue.
     *
     * @return array array of tuples where the first element is the local file path (path inside the PHAR) and the
     *               second element is the processed contents
     */
    private function processContents(array $files, string $cwd): array
    {
        $basePath = $this->basePath;
        $mapFile = $this->mapFile;
        $placeholders = $this->placeholders;
        $compactors = $this->compactors;
        $bootstrap = $GLOBALS['bootstrap'] ?? function (): void {};

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

        return is_debug_enabled()
            ? array_map($processFile, $files)
            : wait(parallelMap($files, $processFile))
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
}
