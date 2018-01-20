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

use ArrayIterator;
use Closure;
use DateTimeImmutable;
use Herrera\Annotations\Tokenizer;
use Herrera\Box\Compactor\Php as LegacyPhp;
use InvalidArgumentException;
use KevinGH\Box\Compactor\Php;
use Phar;
use RuntimeException;
use SplFileInfo;
use stdClass;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

final class Configuration
{
    private const DEFAULT_ALIAS = 'default.phar';
    private const DEFAULT_DATETIME_FORMAT = 'Y-m-d H:i:s';
    private const DEFAULT_REPLACEMENT_SIGIL = '@';

    private $fileMode;
    private $alias;
    private $basePath;
    private $basePathRegex;
    private $binaryDirectoriesIterator;
    private $binaryFilesIterator;
    private $binaryIterators;
    private $directoriesIterator;
    private $filesIterator;
    private $filesIterators;
    private $bootstrapFile;
    private $compactors;
    private $compressionAlgorithm;
    private $mainScriptPath;
    private $mainScriptContent;
    private $map;
    private $mapper;
    private $metadata;
    private $mimetypeMapping;
    private $mungVariables;
    private $notFoundScriptPath;
    private $outputPath;
    private $privateKeyPassphrase;
    private $privateKeyPath;
    private $isPrivateKeyPrompt;
    private $processedReplacements;
    private $shebang;
    private $signingAlgorithm;
    private $stubBanner;
    private $stubBannerPath;
    private $stubBannerFromFile;
    private $stubPath;
    private $isExtractable;
    private $isInterceptFileFuncs;
    private $isStubGenerated;
    private $isWebPhar;

    /**
     * @param string                     $alias                     TODO: description
     * @param string                     $basePath                  Path used as the base for all the relative paths used
     * @param string                     $basePathRegex             Base path escaped to be able to easily extract the relative path from a file path
     * @param iterable|SplFileInfo[]     $binaryDirectoriesIterator List of directories containing images or other binary data
     * @param iterable|SplFileInfo[]     $binaryFilesIterator       List of files containing images or other binary data
     * @param iterable[]|SplFileInfo[][] $binaryIterators           List of file iterators returning binary files
     * @param iterable|SplFileInfo[]     $directoriesIterator       List of directories
     * @param iterable|SplFileInfo[]     $filesIterator             List of files
     * @param iterable[]|SplFileInfo[][] $filesIterators            List of file iterators
     * @param null|string                $bootstrapFile             The bootstrap file path
     * @param Compactor[]                $compactors                List of file contents compactors
     * @param null|int                   $compressionAlgorithm      Compression algorithm constant value. See the \Phar class constants
     * @param null|int                   $fileMode                  File mode in octal form
     * @param null|string                $mainScriptPath            The main script file path
     * @param null|string                $mainScriptContent         The processed content of the main script file
     * @param string[]                   $map                       The internal file path mapping
     * @param Closure                    $mapper                    Callable for the configured map
     * @param mixed                      $metadata                  The PHAR Metadata
     * @param array                      $mimetypeMapping           The file extension MIME type mapping
     * @param array                      $mungVariables             The list of server variables to modify for execution
     * @param null|string                $notFoundScriptPath        The file path to the script to execute when a file is not found
     * @param string                     $outputPath
     * @param null|string                $privateKeyPassphrase
     * @param null|string                $privateKeyPath
     * @param bool                       $isPrivateKeyPrompt        If the user should be prompted for the private key passphrase
     * @param array                      $processedReplacements     The processed list of replacement placeholders and their values
     * @param null|string                $shebang                   The shebang line
     * @param int                        $signingAlgorithm          The PHAR siging algorithm. See \Phar constants
     * @param null|string                $stubBanner                The stub banner comment
     * @param null|string                $stubBannerPath            The path to the stub banner comment file
     * @param null|string                $stubBannerFromFile        The stub banner comment from the fine
     * @param null|string                $stubPath                  The PHAR stub file path
     * @param bool                       $isExtractable             Wether or not StubGenerator::extract() should be used
     * @param bool                       $isInterceptFileFuncs      wether or not Phar::interceptFileFuncs() should be used
     * @param bool                       $isStubGenerated           Wether or not if the PHAR stub should be generated
     * @param bool                       $isWebPhar                 Wether or not the PHAR is going to be used for the web
     */
    private function __construct(
        string $alias,
        string $basePath,
        string $basePathRegex,
        ?iterable $binaryDirectoriesIterator,
        ?iterable $binaryFilesIterator,
        array $binaryIterators,
        ?iterable $directoriesIterator,
        ?iterable $filesIterator,
        array $filesIterators,
        ?string $bootstrapFile,
        array $compactors,
        ?int $compressionAlgorithm,
        ?int $fileMode,
        ?string $mainScriptPath,
        ?string $mainScriptContent,
        array $map,
        Closure $mapper,
        $metadata,
        array $mimetypeMapping,
        array $mungVariables,
        ?string $notFoundScriptPath,
        string $outputPath,
        ?string $privateKeyPassphrase,
        ?string $privateKeyPath,
        bool $isPrivateKeyPrompt,
        array $processedReplacements,
        ?string $shebang,
        int $signingAlgorithm,
        ?string $stubBanner,
        ?string $stubBannerPath,
        ?string $stubBannerFromFile,
        ?string $stubPath,
        bool $isExtractable,
        bool $isInterceptFileFuncs,
        bool $isStubGenerated,
        bool $isWebPhar
    ) {
        $this->alias = $alias;
        $this->basePath = $basePath;
        $this->basePathRegex = $basePathRegex;
        $this->binaryDirectoriesIterator = $binaryDirectoriesIterator;
        $this->binaryFilesIterator = $binaryFilesIterator;
        $this->binaryIterators = $binaryIterators;
        $this->directoriesIterator = $directoriesIterator;
        $this->filesIterator = $filesIterator;
        $this->filesIterators = $filesIterators;
        $this->bootstrapFile = $bootstrapFile;
        $this->compactors = $compactors;
        $this->compressionAlgorithm = $compressionAlgorithm;
        $this->fileMode = $fileMode;
        $this->mainScriptPath = $mainScriptPath;
        $this->mainScriptContent = $mainScriptContent;
        $this->map = $map;
        $this->mapper = $mapper;
        $this->metadata = $metadata;
        $this->mimetypeMapping = $mimetypeMapping;
        $this->mungVariables = $mungVariables;
        $this->notFoundScriptPath = $notFoundScriptPath;
        $this->outputPath = $outputPath;
        $this->privateKeyPassphrase = $privateKeyPassphrase;
        $this->privateKeyPath = $privateKeyPath;
        $this->isPrivateKeyPrompt = $isPrivateKeyPrompt;
        $this->processedReplacements = $processedReplacements;
        $this->shebang = $shebang;
        $this->signingAlgorithm = $signingAlgorithm;
        $this->stubBanner = $stubBanner;
        $this->stubBannerPath = $stubBannerPath;
        $this->stubBannerFromFile = $stubBannerFromFile;
        $this->stubPath = $stubPath;
        $this->isExtractable = $isExtractable;
        $this->isInterceptFileFuncs = $isInterceptFileFuncs;
        $this->isStubGenerated = $isStubGenerated;
        $this->isWebPhar = $isWebPhar;
    }

    public static function create(string $file, stdClass $raw): self
    {
        $alias = $raw->alias ?? self::DEFAULT_ALIAS;

        $basePath = self::retrieveBasePath($file, $raw);
        $basePathRegex = '/'.preg_quote($basePath.DIRECTORY_SEPARATOR, '/').'/';

        $blacklist = self::retrieveBlacklist($raw);
        $blacklistFilter = self::retrieveBlacklistFilter($basePath, $blacklist);

        $binaryDirectories = self::retrieveBinaryDirectories($raw, $basePath);
        $binaryDirectoriesIterator = self::retrieveBinaryDirectoriesIterator($binaryDirectories, $blacklistFilter);

        $binaryFiles = self::retrieveBinaryFiles($raw, $basePath);
        $binaryFilesIterator = self::retrieveBinaryFilesIterator($binaryFiles);

        $binaryIterators = self::retrieveBinaryIterators($raw, $basePath, $blacklistFilter);

        $directories = self::retrieveDirectories($raw, $basePath);
        $directoriesIterator = self::retrieveDirectoriesIterator($directories, $blacklistFilter);

        $files = self::retrieveFiles($raw, $basePath);
        $filesIterator = self::retrieveFilesIterator($files);

        $filesIterators = self::retrieveFilesIterators($raw, $basePath, $blacklistFilter);

        $bootstrapFile = self::retrieveBootstrapFile($raw, $basePath);

        $compactors = self::retrieveCompactors($raw);
        $compressionAlgorithm = self::retrieveCompressionAlgorithm($raw);

        $fileMode = self::retrieveFileMode($raw);

        $mainScriptPath = self::retrieveMainScriptPath($raw);
        $mainScriptContent = self::retrieveMainScriptContents($mainScriptPath, $basePath);

        $map = self::retrieveMap($raw);
        $mapper = self::retrieveMapper($map);

        $metadata = self::retrieveMetadata($raw);

        $mimeTypeMapping = self::retrieveMimetypeMapping($raw);
        $mungVariables = self::retrieveMungVariables($raw);
        $notFoundScriptPath = self::retrieveNotFoundScriptPath($raw);
        $outputPath = self::retrieveOutputPath($raw, $file);

        $privateKeyPassphrase = self::retrievePrivateKeyPassphrase($raw);
        $privateKeyPath = self::retrievePrivateKeyPath($raw);
        $isPrivateKeyPrompt = self::retrieveIsPrivateKeyPrompt($raw);

        $replacements = self::retrieveReplacements($raw);
        $processedReplacements = self::retrieveProcessedReplacements($replacements, $raw, $file);

        $shebang = self::retrieveShebang($raw);

        $signingAlgorithm = self::retrieveSigningAlgorithm($raw);

        $stubBanner = self::retrieveStubBanner($raw);
        $stubBannerPath = self::retrieveStubBannerPath($raw);
        $stubBannerFromFile = self::retrieveStubBannerFromFile($basePath, $stubBannerPath);

        $stubPath = self::retrieveStubPath($raw);

        $isExtractable = self::retrieveIsExtractable($raw);
        $isInterceptFileFuncs = self::retrieveIsInterceptFileFuncs($raw);
        $isStubGenerated = self::retrieveIsStubGenerated($raw);
        $isWebPhar = self::retrieveIsWebPhar($raw);

        return new self(
            $alias,
            $basePath,
            $basePathRegex,
            $binaryDirectoriesIterator,
            $binaryFilesIterator,
            $binaryIterators,
            $directoriesIterator,
            $filesIterator,
            $filesIterators,
            $bootstrapFile,
            $compactors,
            $compressionAlgorithm,
            $fileMode,
            $mainScriptPath,
            $mainScriptContent,
            $map,
            $mapper,
            $metadata,
            $mimeTypeMapping,
            $mungVariables,
            $notFoundScriptPath,
            $outputPath,
            $privateKeyPassphrase,
            $privateKeyPath,
            $isPrivateKeyPrompt,
            $processedReplacements,
            $shebang,
            $signingAlgorithm,
            $stubBanner,
            $stubBannerPath,
            $stubBannerFromFile,
            $stubPath,
            $isExtractable,
            $isInterceptFileFuncs,
            $isStubGenerated,
            $isWebPhar
        );
    }

    public function retrieveRelativeBasePath(string $path): string
    {
        return preg_replace(
            $this->basePathRegex,
            '',
            $path
        );
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * @return null|iterable|SplFileInfo[]
     */
    public function getBinaryDirectoriesIterator(): ?iterable
    {
        return $this->binaryDirectoriesIterator;
    }

    /**
     * @return null|iterable|SplFileInfo[]
     */
    public function getBinaryFilesIterator(): ?iterable
    {
        return $this->binaryFilesIterator;
    }

    /**
     * @return iterable[]|SplFileInfo[][]
     */
    public function getBinaryIterators(): array
    {
        return $this->binaryIterators;
    }

    /**
     * @return null|iterable|SplFileInfo[]
     */
    public function getDirectoriesIterator(): ?iterable
    {
        return $this->directoriesIterator;
    }

    /**
     * @return null|iterable|SplFileInfo[]
     */
    public function getFilesIterator(): ?iterable
    {
        return $this->filesIterator;
    }

    public function getBootstrapFile(): ?string
    {
        return $this->bootstrapFile;
    }

    public function loadBootstrap(): void
    {
        $file = $this->bootstrapFile;

        if (null !== $file) {
            include $file;
        }
    }

    /**
     * @return Compactor[] the list of compactors
     */
    public function getCompactors(): array
    {
        return $this->compactors;
    }

    public function getCompressionAlgorithm(): ?int
    {
        return $this->compressionAlgorithm;
    }

    public function getFileMode(): ?int
    {
        return $this->fileMode;
    }

    public function getMainScriptPath(): ?string
    {
        return $this->mainScriptPath;
    }

    public function getMainScriptContent(): ?string
    {
        return $this->mainScriptContent;
    }

    public function getMimetypeMapping(): array
    {
        return $this->mimetypeMapping;
    }

    public function getMungVariables(): array
    {
        return $this->mungVariables;
    }

    public function getNotFoundScriptPath(): ?string
    {
        return $this->notFoundScriptPath;
    }

    public function getOutputPath(): string
    {
        return $this->outputPath;
    }

    /**
     * @return string[]
     */
    public function getMap(): array
    {
        return $this->map;
    }

    public function getMapper(): Closure
    {
        return $this->mapper;
    }

    /**
     * @return mixed
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @return iterable[]|SplFileInfo[][]
     */
    public function getFilesIterators()
    {
        return $this->filesIterators;
    }

    public function getPrivateKeyPassphrase(): ?string
    {
        return $this->privateKeyPassphrase;
    }

    public function getPrivateKeyPath(): ?string
    {
        return $this->privateKeyPath;
    }

    public function isPrivateKeyPrompt(): bool
    {
        return $this->isPrivateKeyPrompt;
    }

    public function getProcessedReplacements(): array
    {
        return $this->processedReplacements;
    }

    public function getShebang(): ?string
    {
        return $this->shebang;
    }

    public function getSigningAlgorithm(): int
    {
        return $this->signingAlgorithm;
    }

    public function getStubBanner(): ?string
    {
        return $this->stubBanner;
    }

    public function getStubBannerPath(): ?string
    {
        return $this->stubBannerPath;
    }

    public function getStubBannerFromFile()
    {
        return $this->stubBannerFromFile;
    }

    public function getStubPath(): ?string
    {
        return $this->stubPath;
    }

    public function isExtractable(): bool
    {
        return $this->isExtractable;
    }

    public function isInterceptFileFuncs(): bool
    {
        return $this->isInterceptFileFuncs;
    }

    public function isStubGenerated(): bool
    {
        return $this->isStubGenerated;
    }

    public function isWebPhar(): bool
    {
        return $this->isWebPhar;
    }

    private static function retrieveBasePath(string $file, stdClass $raw): string
    {
        if (isset($raw->{'base-path'})) {
            if (false === is_dir($raw->{'base-path'})) {
                throw new InvalidArgumentException(
                    sprintf(
                        'The base path "%s" is not a directory or does not exist.',
                        $raw->{'base-path'}
                    )
                );
            }

            return realpath($raw->{'base-path'});
        }

        return realpath(dirname($file));
    }

    /**
     * @return SplFileInfo[]
     */
    private static function retrieveBinaryDirectories(stdClass $raw, string $basePath): array
    {
        if (isset($raw->{'directories-bin'})) {
            $directories = (array) $raw->{'directories-bin'};

            array_walk(
                $directories,
                function (&$directory) use ($basePath): void {
                    $directory = $basePath
                        .DIRECTORY_SEPARATOR
                        .canonicalize($directory);
                }
            );

            return $directories;
        }

        return [];
    }

    /**
     * @param SplFileInfo[] $binaryDirectories
     * @param Closure       $blacklistFilter
     *
     * @return null|iterable|SplFileInfo[] the iterator
     */
    private static function retrieveBinaryDirectoriesIterator(array $binaryDirectories, Closure $blacklistFilter): ?iterable
    {
        if ([] !== $binaryDirectories) {
            return Finder::create()
                ->files()
                ->filter($blacklistFilter)
                ->ignoreVCS(true)
                ->in($binaryDirectories);
        }

        return null;
    }

    /**
     * @return SplFileInfo[] the list of paths
     */
    private static function retrieveBinaryFiles(stdClass $raw, string $basePath): array
    {
        if (isset($raw->{'files-bin'})) {
            $files = [];

            foreach ((array) $raw->{'files-bin'} as $file) {
                $files[] = new SplFileInfo(
                    $basePath.DIRECTORY_SEPARATOR.canonicalize($file)
                );
            }

            return $files;
        }

        return [];
    }

    /**
     * @return null|iterable|SplFileInfo[] the iterator
     */
    private static function retrieveBinaryFilesIterator(array $binaryFiles): ?iterable
    {
        if ([] !== $binaryFiles) {
            return new ArrayIterator($binaryFiles);
        }

        return null;
    }

    /**
     * @return string[]
     */
    private static function retrieveBlacklist(stdClass $raw): array
    {
        if (isset($raw->blacklist)) {
            $blacklist = (array) $raw->blacklist;

            array_walk(
                $blacklist,
                function (&$file): void {
                    $file = canonicalize($file);
                }
            );

            return $blacklist;
        }

        return [];
    }

    /**
     * @param string   $basePath
     * @param string[] $blacklist
     *
     * @return Closure
     */
    private static function retrieveBlacklistFilter(string $basePath, array $blacklist): Closure
    {
        $base = sprintf(
            '/^%s/',
            preg_quote($basePath.DIRECTORY_SEPARATOR, '/')
        );

        return function (SplFileInfo $file) use ($base, $blacklist): ?bool {
            $path = canonicalize(
                preg_replace($base, '', $file->getPathname())
            );

            if (in_array($path, $blacklist, true)) {
                return false;
            }

            return null;
        };
    }

    /**
     * @param stdClass $raw
     * @param string   $basePath
     * @param Closure  $blacklistFilter
     *
     * @return iterable[]|SplFileInfo[][]
     */
    private static function retrieveBinaryIterators(stdClass $raw, string $basePath, Closure $blacklistFilter): array
    {
        if (isset($raw->{'finder-bin'})) {
            return self::processFinders($raw->{'finder-bin'}, $basePath, $blacklistFilter);
        }

        return [];
    }

    /**
     * @param stdClass $raw
     * @param string   $basePath
     *
     * @return string[]
     */
    private static function retrieveDirectories(stdClass $raw, string $basePath): array
    {
        if (isset($raw->directories)) {
            $directories = (array) $raw->directories;

            array_walk(
                $directories,
                function (&$directory) use ($basePath): void {
                    $directory = $basePath
                        .DIRECTORY_SEPARATOR
                        .rtrim(canonicalize($directory), DIRECTORY_SEPARATOR);
                }
            );

            return $directories;
        }

        return [];
    }

    /**
     * @param string[] $directories
     * @param Closure  $blacklistFilter
     *
     * @return null|iterable|SplFileInfo[]
     */
    private static function retrieveDirectoriesIterator(array $directories, Closure $blacklistFilter): ?iterable
    {
        if ([] !== $directories) {
            return Finder::create()
                ->files()
                ->filter($blacklistFilter)
                ->ignoreVCS(true)
                ->in($directories)
            ;
        }

        return null;
    }

    /**
     * @return SplFileInfo[]
     */
    private static function retrieveFiles(stdClass $raw, string $basePath): array
    {
        if (false === isset($raw->files)) {
            return [];
        }

        $files = [];

        foreach ((array) $raw->files as $file) {
            $file = new SplFileInfo(
                $path = $basePath.DIRECTORY_SEPARATOR.canonicalize($file)
            );

            if (false === $file->isFile()) {
                throw new InvalidArgumentException(
                    sprintf(
                        'The file "%s" does not exist or is not a file.',
                        $path
                    )
                );
            }

            $files[] = $file;
        }

        return $files;
    }

    /**
     * @param SplFileInfo[] $files
     *
     * @return null|iterable|SplFileInfo[]
     */
    private static function retrieveFilesIterator(array $files): ?iterable
    {
        if ([] !== $files) {
            return new ArrayIterator($files);
        }

        return null;
    }

    /**
     * @param stdClass $raw
     * @param string   $basePath
     * @param Closure  $blacklistFilter
     *
     * @return iterable[]|SplFileInfo[][]
     */
    private static function retrieveFilesIterators(stdClass $raw, string $basePath, Closure $blacklistFilter): array
    {
        if (isset($raw->finder)) {
            return self::processFinders($raw->finder, $basePath, $blacklistFilter);
        }

        return [];
    }

    /**
     * @param array   $findersConfig   the configuration
     * @param string  $basePath
     * @param Closure $blacklistFilter
     *
     * @return Finder[]
     */
    private static function processFinders(array $findersConfig, string $basePath, Closure $blacklistFilter): array
    {
        $processFinderConfig = function ($methods) use ($basePath, $blacklistFilter): Finder {
            $finder = Finder::create()
                ->files()
                ->filter($blacklistFilter)
                ->ignoreVCS(true)
            ;

            if (isset($methods->in)) {
                $methods->in = (array) $methods->in;

                array_walk(
                    $methods->in,
                    function (&$directory) use ($basePath): void {
                        $directory = canonicalize(
                            $basePath.DIRECTORY_SEPARATOR.$directory
                        );
                    }
                );
            }

            foreach ($methods as $method => $arguments) {
                if (false === method_exists($finder, $method)) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'The method "Finder::%s" does not exist.',
                            $method
                        )
                    );
                }

                $arguments = (array) $arguments;

                foreach ($arguments as $argument) {
                    $finder->$method($argument);
                }
            }

            return $finder;
        };

        return array_map($processFinderConfig, $findersConfig);
    }

    private static function retrieveBootstrapFile(stdClass $raw, string $basePath): ?string
    {
        if (false === isset($raw->bootstrap)) {
            return null;
        }

        $file = $raw->bootstrap;

        if (false === is_absolute($file)) {
            $file = canonicalize(
                $basePath.DIRECTORY_SEPARATOR.$file
            );
        }

        if (false === file_exists($file)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The bootstrap path "%s" is not a file or does not exist.',
                    $file
                )
            );
        }

        return $file;
    }

    /**
     * @return Compactor[]
     */
    private static function retrieveCompactors(stdClass $raw): array
    {
        if (false === isset($raw->compactors)) {
            return [];
        }

        $compactors = [];

        foreach ((array) $raw->compactors as $class) {
            if (false === class_exists($class)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'The compactor class "%s" does not exist.',
                        $class
                    )
                );
            }

            if (Php::class === $class || LegacyPhp::class === $class) {
                $compactor = self::createPhpCompactor($raw);
            } else {
                $compactor = new $class();
            }

            if (false === ($compactor instanceof Compactor)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'The class "%s" is not a compactor class.',
                        $class
                    )
                );
            }

            $compactors[] = $compactor;
        }

        return $compactors;
    }

    private static function retrieveCompressionAlgorithm(stdClass $raw): ?int
    {
        if (false === isset($raw->compression)) {
            return null;
        }

        if (false === is_string($raw->compression)) {
            return $raw->compression;
        }

        if (false === defined('Phar::'.$raw->compression)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The compression algorithm "%s" is not supported.',
                    $raw->compression
                )
            );
        }

        $value = constant('Phar::'.$raw->compression);

        // Phar::NONE is not valid for compressFiles()
        if (Phar::NONE === $value) {
            return null;
        }

        return $value;
    }

    private static function retrieveFileMode(stdClass $raw): ?int
    {
        if (isset($raw->chmod)) {
            return intval($raw->chmod, 8);
        }

        return null;
    }

    private static function retrieveMainScriptPath(stdClass $raw): ?string
    {
        if (isset($raw->main)) {
            return canonicalize($raw->main);
        }

        return null;
    }

    private static function retrieveMainScriptContents(?string $mainScriptPath, string $basePath): ?string
    {
        if (null === $mainScriptPath) {
            return null;
        }
        $mainScriptPath = $basePath.DIRECTORY_SEPARATOR.$mainScriptPath;

        if (false === ($contents = @file_get_contents($mainScriptPath))) {
            $errors = error_get_last();

            if (null === $errors) {
                $errors = ['message' => 'Failed to get contents of "'.$mainScriptPath.'""'];
            }

            throw new InvalidArgumentException($errors['message']);
        }

        return preg_replace('/^#!.*\s*/', '', $contents);
    }

    /**
     * @return string[]
     */
    private static function retrieveMap(stdClass $raw): array
    {
        if (false === isset($raw->map)) {
            return [];
        }

        $map = [];

        foreach ((array) $raw->map as $item) {
            $processed = [];

            foreach ($item as $match => $replace) {
                $processed[canonicalize($match)] = canonicalize($replace);
            }

            if (isset($processed['_empty_'])) {
                $processed[''] = $processed['_empty_'];

                unset($processed['_empty_']);
            }

            $map[] = $processed;
        }

        return $map;
    }

    private static function retrieveMapper(array $map): Closure
    {
        return function (string $path) use ($map): ?string {
            foreach ($map as $item) {
                foreach ($item as $match => $replace) {
                    if (empty($match)) {
                        return $replace.$path;
                    }

                    if (0 === strpos($path, $match)) {
                        return preg_replace(
                            '/^'.preg_quote($match, '/').'/',
                            $replace,
                            $path
                        );
                    }
                }
            }

            return null;
        };
    }

    /**
     * @return mixed
     */
    private static function retrieveMetadata(stdClass $raw)
    {
        if (isset($raw->metadata)) {
            if (is_object($raw->metadata)) {
                return (array) $raw->metadata;
            }

            return $raw->metadata;
        }

        return null;
    }

    private static function retrieveMimetypeMapping(stdClass $raw): array
    {
        if (isset($raw->mimetypes)) {
            return (array) $raw->mimetypes;
        }

        return [];
    }

    private static function retrieveMungVariables(stdClass $raw): array
    {
        if (isset($raw->mung)) {
            return (array) $raw->mung;
        }

        return [];
    }

    private static function retrieveNotFoundScriptPath(stdClass $raw): ?string
    {
        if (isset($raw->{'not-found'})) {
            return $raw->{'not-found'};
        }

        return null;
    }

    private static function retrieveOutputPath(stdClass $raw, string $file): string
    {
        $base = getcwd().DIRECTORY_SEPARATOR;

        if (isset($raw->output)) {
            $path = $raw->output;

            if (false === is_absolute($path)) {
                $path = canonicalize($base.$path);
            }
        } else {
            $path = $base.self::DEFAULT_ALIAS;
        }

        if (false !== strpos($path, '@'.'git-version@')) {
            $gitVersion = self::retrieveGitVersion($file);

            $path = str_replace('@'.'git-version@', $gitVersion, $path);
        }

        return $path;
    }

    private static function retrievePrivateKeyPassphrase(stdClass $raw): ?string
    {
        if (isset($raw->{'key-pass'})
            && is_string($raw->{'key-pass'})
        ) {
            return $raw->{'key-pass'};
        }

        return null;
    }

    private static function retrievePrivateKeyPath(stdClass $raw): ?string
    {
        if (isset($raw->key)) {
            return $raw->key;
        }

        return null;
    }

    private static function retrieveReplacements(stdClass $raw): array
    {
        if (isset($raw->replacements)) {
            return (array) $raw->replacements;
        }

        return [];
    }

    private static function retrieveProcessedReplacements(
        array $replacements,
        stdClass $raw,
        string $file
    ): array {
        if (null !== ($git = self::retrieveGitHashPlaceholder($raw))) {
            $replacements[$git] = self::retrieveGitHash($file);
        }

        if (null !== ($git = self::retrieveGitShortHashPlaceholder($raw))) {
            $replacements[$git] = self::retrieveGitHash($file, true);
        }

        if (null !== ($git = self::retrieveGitTagPlaceholder($raw))) {
            $replacements[$git] = self::retrieveGitTag($file);
        }

        if (null !== ($git = self::retrieveGitVersionPlaceholder($raw))) {
            $replacements[$git] = self::retrieveGitVersion($file);
        }

        if (null !== ($date = self::retrieveDatetimeNowPlaceHolder($raw))) {
            $replacements[$date] = self::retrieveDatetimeNow(
                self::retrieveDatetimeFormat($raw)
            );
        }

        $sigil = self::retrieveReplacementSigil($raw);

        foreach ($replacements as $key => $value) {
            unset($replacements[$key]);
            $replacements["$sigil$key$sigil"] = $value;
        }

        return $replacements;
    }

    private static function retrieveGitHashPlaceholder(stdClass $raw): ?string
    {
        if (isset($raw->{'git-commit'})) {
            return $raw->{'git-commit'};
        }

        return null;
    }

    /**
     * @param string $file
     * @param bool   $short Use the short version
     *
     * @return string the commit hash
     */
    private static function retrieveGitHash(string $file, bool $short = false): string
    {
        return self::runGitCommand(
            sprintf(
                'git log --pretty="%s" -n1 HEAD',
                $short ? '%h' : '%H'
            ),
            $file
        );
    }

    private static function retrieveGitShortHashPlaceholder(stdClass $raw): ?string
    {
        if (isset($raw->{'git-commit-short'})) {
            return $raw->{'git-commit-short'};
        }

        return null;
    }

    private static function retrieveGitTagPlaceholder(stdClass $raw): ?string
    {
        if (isset($raw->{'git-tag'})) {
            return $raw->{'git-tag'};
        }

        return null;
    }

    private static function retrieveGitTag(string $file): ?string
    {
        return self::runGitCommand('git describe --tags HEAD', $file);
    }

    private static function retrieveGitVersionPlaceholder(stdClass $raw): ?string
    {
        if (isset($raw->{'git-version'})) {
            return $raw->{'git-version'};
        }

        return null;
    }

    private static function retrieveGitVersion(string $file): ?string
    {
        try {
            return self::retrieveGitTag($file);
        } catch (RuntimeException $exception) {
            try {
                return self::retrieveGitHash($file, true);
            } catch (RuntimeException $exception) {
                throw new RuntimeException(
                    sprintf(
                        'The tag or commit hash could not be retrieved from "%s": %s',
                        dirname($file),
                        $exception->getMessage()
                    ),
                    0,
                    $exception
                );
            }
        }
    }

    private static function retrieveDatetimeNowPlaceHolder(stdClass $raw): ?string
    {
        if (isset($raw->{'datetime'})) {
            return $raw->{'datetime'};
        }

        return null;
    }

    private static function retrieveDatetimeNow(string $format)
    {
        $now = new DateTimeImmutable('now');

        $datetime = $now->format($format);

        if (!$datetime) {
            throw new InvalidArgumentException(
                sprintf(
                    '""%s" is not a valid PHP date format',
                    $format
                )
            );
        }

        return $datetime;
    }

    private static function retrieveDatetimeFormat(stdClass $raw): string
    {
        if (isset($raw->{'datetime_format'})) {
            return $raw->{'datetime_format'};
        }

        return self::DEFAULT_DATETIME_FORMAT;
    }

    private static function retrieveReplacementSigil(stdClass $raw)
    {
        if (isset($raw->{'replacement-sigil'})) {
            return $raw->{'replacement-sigil'};
        }

        return self::DEFAULT_REPLACEMENT_SIGIL;
    }

    private static function retrieveShebang(stdClass $raw): ?string
    {
        if (false === isset($raw->shebang)) {
            return null;
        }

        if (('' === $raw->shebang) || (false === $raw->shebang)) {
            return '';
        }

        $shebang = trim($raw->shebang);

        if ('#!' !== substr($shebang, 0, 2)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The shebang line must start with "#!": %s',
                    $shebang
                )
            );
        }

        return $shebang;
    }

    private static function retrieveSigningAlgorithm(stdClass $raw): int
    {
        if (false === isset($raw->algorithm)) {
            return Phar::SHA1;
        }

        if (is_string($raw->algorithm)) {
            if (false === defined('Phar::'.$raw->algorithm)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'The signing algorithm "%s" is not supported.',
                        $raw->algorithm
                    )
                );
            }

            return constant('Phar::'.$raw->algorithm);
        }

        return $raw->algorithm;
    }

    private static function retrieveStubBanner(stdClass $raw): ?string
    {
        if (isset($raw->{'banner'})) {
            return $raw->{'banner'};
        }

        return null;
    }

    private static function retrieveStubBannerPath(stdClass $raw): ?string
    {
        if (isset($raw->{'banner-file'})) {
            return canonicalize($raw->{'banner-file'});
        }

        return null;
    }

    private static function retrieveStubBannerFromFile(string $basePath, ?string $stubBannerPath): ?string
    {
        if (null == $stubBannerPath) {
            return null;
        }

        $stubBannerPath = $basePath.DIRECTORY_SEPARATOR.$stubBannerPath;

        if (false === ($contents = @file_get_contents($stubBannerPath))) {
            $errors = error_get_last();

            if (null === $errors) {
                $errors = ['message' => 'failed to get contents of "'.$stubBannerPath.'""'];
            }

            throw new InvalidArgumentException($errors['message']);
        }

        return $contents;
    }

    private static function retrieveStubPath(stdClass $raw): ?string
    {
        if (isset($raw->stub) && is_string($raw->stub)) {
            return $raw->stub;
        }

        return null;
    }

    private static function retrieveIsExtractable(stdClass $raw): bool
    {
        if (isset($raw->extract)) {
            return $raw->extract;
        }

        return false;
    }

    private static function retrieveIsInterceptFileFuncs(stdClass $raw): bool
    {
        if (isset($raw->intercept)) {
            return $raw->intercept;
        }

        return false;
    }

    private static function retrieveIsPrivateKeyPrompt(stdClass $raw): bool
    {
        if (isset($raw->{'key-pass'})
            && (true === $raw->{'key-pass'})) {
            return true;
        }

        return false;
    }

    private static function retrieveIsStubGenerated(stdClass $raw): bool
    {
        if (isset($raw->stub) && (true === $raw->stub)) {
            return true;
        }

        return false;
    }

    private static function retrieveIsWebPhar(stdClass $raw): bool
    {
        if (isset($raw->web)) {
            return $raw->web;
        }

        return false;
    }

    /**
     * Runs a Git command on the repository.
     *
     * @param string $command the command
     *
     * @return string the trimmed output from the command
     */
    private static function runGitCommand(string $command, string $file): string
    {
        $path = dirname($file);

        $process = new Process($command, $path);

        if (0 === $process->run()) {
            return trim($process->getOutput());
        }

        throw new RuntimeException(
            sprintf(
                'The tag or commit hash could not be retrieved from "%s": %s',
                $path,
                $process->getErrorOutput()
            )
        );
    }

    private static function createPhpCompactor(stdClass $raw): Compactor
    {
        $tokenizer = new Tokenizer();

        if (false === empty($raw->annotations) && isset($raw->annotations->ignore)) {
            $tokenizer->ignore(
                (array) $raw->annotations->ignore
            );
        }

        return new Php($tokenizer);
    }
}
