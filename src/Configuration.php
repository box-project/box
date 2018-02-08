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
use function iter\chain;
use function KevinGH\Box\FileSystem\canonicalize;
use function KevinGH\Box\FileSystem\file_contents;
use function KevinGH\Box\FileSystem\is_absolute_path;
use function KevinGH\Box\FileSystem\make_path_absolute;

final class Configuration
{
    private const DEFAULT_ALIAS = 'default.phar';
    private const DEFAULT_DATETIME_FORMAT = 'Y-m-d H:i:s';
    private const DEFAULT_REPLACEMENT_SIGIL = '@';

    private $fileMode;
    private $alias;
    private $basePathRetriever;
    private $files;
    private $binaryFiles;
    private $bootstrapFile;
    private $compactors;
    private $compressionAlgorithm;
    private $mainScriptPath;
    private $mainScriptContent;
    private $map;
    private $fileMapper;
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
     * @param string                   $alias
     * @param RetrieveRelativeBasePath $basePathRetriever     Utility to private the base path used and be able to retrieve a path relative to it (the base path)
     * @param SplFileInfo[]            $files                 List of files
     * @param SplFileInfo[]            $binaryFiles           List of binary files
     * @param null|string              $bootstrapFile         The bootstrap file path
     * @param Compactor[]              $compactors            List of file contents compactors
     * @param null|int                 $compressionAlgorithm  Compression algorithm constant value. See the \Phar class constants
     * @param null|int                 $fileMode              File mode in octal form
     * @param null|string              $mainScriptPath        The main script file path
     * @param null|string              $mainScriptContent     The processed content of the main script file
     * @param MapFile                  $fileMapper            Utility to map the files from outside and inside the PHAR
     * @param mixed                    $metadata              The PHAR Metadata
     * @param array                    $mimetypeMapping       The file extension MIME type mapping
     * @param array                    $mungVariables         The list of server variables to modify for execution
     * @param null|string              $notFoundScriptPath    The file path to the script to execute when a file is not found
     * @param string                   $outputPath
     * @param null|string              $privateKeyPassphrase
     * @param null|string              $privateKeyPath
     * @param bool                     $isPrivateKeyPrompt    If the user should be prompted for the private key passphrase
     * @param array                    $processedReplacements The processed list of replacement placeholders and their values
     * @param null|string              $shebang               The shebang line
     * @param int                      $signingAlgorithm      The PHAR siging algorithm. See \Phar constants
     * @param null|string              $stubBanner            The stub banner comment
     * @param null|string              $stubBannerPath        The path to the stub banner comment file
     * @param null|string              $stubBannerFromFile    The stub banner comment from the fine
     * @param null|string              $stubPath              The PHAR stub file path
     * @param bool                     $isExtractable         Wether or not StubGenerator::extract() should be used
     * @param bool                     $isInterceptFileFuncs  wether or not Phar::interceptFileFuncs() should be used
     * @param bool                     $isStubGenerated       Wether or not if the PHAR stub should be generated
     * @param bool                     $isWebPhar             Wether or not the PHAR is going to be used for the web
     */
    private function __construct(
        string $alias,
        RetrieveRelativeBasePath $basePathRetriever,
        array $files,
        array $binaryFiles,
        ?string $bootstrapFile,
        array $compactors,
        ?int $compressionAlgorithm,
        ?int $fileMode,
        ?string $mainScriptPath,
        ?string $mainScriptContent,
        MapFile $fileMapper,
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
        Assertion::nullOrInArray(
            $compressionAlgorithm,
            get_phar_compression_algorithms(),
            sprintf(
                'Invalid compression algorithm "%%s", use one of "%s" instead.',
                implode('", "', array_keys(get_phar_compression_algorithms()))
            )
        );

        $this->alias = $alias;
        $this->basePathRetriever = $basePathRetriever;
        $this->files = $files;
        $this->binaryFiles = $binaryFiles;
        $this->bootstrapFile = $bootstrapFile;
        $this->compactors = $compactors;
        $this->compressionAlgorithm = $compressionAlgorithm;
        $this->fileMode = $fileMode;
        $this->mainScriptPath = $mainScriptPath;
        $this->mainScriptContent = $mainScriptContent;
        $this->fileMapper = $fileMapper;
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
        $alias = self::retrieveAlias($raw);

        $basePath = self::retrieveBasePath($file, $raw);
        $basePathRetriever = new RetrieveRelativeBasePath($basePath);

        $blacklistFilter = self::retrieveBlacklistFilter($raw, $basePath);

        $files = self::retrieveFiles($raw, 'files', $basePath);
        $directories = self::retrieveDirectories($raw, 'directories', $basePath, $blacklistFilter);
        $filesFromFinders = self::retrieveFilesFromFinders($raw, 'finder', $basePath, $blacklistFilter);

        $filesAggregate = array_unique(iterator_to_array(chain($files, $directories, ...$filesFromFinders)));

        $binaryFiles = self::retrieveFiles($raw, 'files-bin', $basePath);
        $binaryDirectories = self::retrieveDirectories($raw, 'directories-bin', $basePath, $blacklistFilter);
        $binaryFilesFromFinders = self::retrieveFilesFromFinders($raw, 'finder-bin', $basePath, $blacklistFilter);

        $binaryFilesAggregate = array_unique(iterator_to_array(chain($binaryFiles, $binaryDirectories, ...$binaryFilesFromFinders)));

        $bootstrapFile = self::retrieveBootstrapFile($raw, $basePath);

        $compactors = self::retrieveCompactors($raw);
        $compressionAlgorithm = self::retrieveCompressionAlgorithm($raw);

        $fileMode = self::retrieveFileMode($raw);

        $mainScriptPath = self::retrieveMainScriptPath($raw);
        $mainScriptContent = self::retrieveMainScriptContents($mainScriptPath, $basePath);

        $map = self::retrieveMap($raw);
        $fileMapper = new MapFile($map);

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
            $basePathRetriever,
            $filesAggregate,
            $binaryFilesAggregate,
            $bootstrapFile,
            $compactors,
            $compressionAlgorithm,
            $fileMode,
            $mainScriptPath,
            $mainScriptContent,
            $fileMapper,
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

    public function getBasePathRetriever(): RetrieveRelativeBasePath
    {
        return $this->basePathRetriever;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getBasePath(): string
    {
        return $this->basePathRetriever->getBasePath();
    }

    /**
     * @return SplFileInfo[]
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * @return SplFileInfo[]
     */
    public function getBinaryFiles(): array
    {
        return $this->binaryFiles;
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
        return $this->fileMapper->getMap();
    }

    public function getFileMapper(): MapFile
    {
        return $this->fileMapper;
    }

    /**
     * @return mixed
     */
    public function getMetadata()
    {
        return $this->metadata;
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

    private static function retrieveAlias(stdClass $raw): string
    {
        $alias = $raw->alias ?? self::DEFAULT_ALIAS;

        $alias = trim($alias);

        Assertion::notEmpty($alias, 'A PHAR alias cannot be empty.');

        return $alias;
    }

    private static function retrieveBasePath(string $file, stdClass $raw): string
    {
        if (false === isset($raw->{'base-path'})) {
            return realpath(dirname($file));
        }

        $basePath = trim($raw->{'base-path'});

        Assertion::directory(
            $basePath,
            'The base path "%s" is not a directory or does not exist.'
        );

        return realpath($basePath);
    }

    /**
     * @param stdClass $raw
     * @param string   $basePath
     *
     * @return Closure
     */
    private static function retrieveBlacklistFilter(stdClass $raw, string $basePath): Closure
    {
        $blacklist = self::retrieveBlacklist($raw, $basePath);

        return function (SplFileInfo $file) use ($blacklist): ?bool {
            if (in_array($file->getRealPath(), $blacklist, true)) {
                return false;
            }

            return null;
        };
    }

    /**
     * @return string[]
     */
    private static function retrieveBlacklist(stdClass $raw, string $basePath): array
    {
        if (false === isset($raw->blacklist)) {
            return [];
        }

        $blacklist = $raw->blacklist;

        $normalizePath = function ($file) use ($basePath): string {
            return self::normalizeFilePath($file, $basePath);
        };

        return array_map($normalizePath, $blacklist);
    }

    /**
     * @param stdClass $raw
     * @param string   $key      Config property name
     * @param string   $basePath
     *
     * @return SplFileInfo[]
     */
    private static function retrieveFiles(stdClass $raw, string $key, string $basePath): array
    {
        if (false === isset($raw->{$key})) {
            return [];
        }

        $files = (array) $raw->{$key};

        Assertion::allString($files);

        $normalizePath = function (string $file) use ($basePath, $key): SplFileInfo {
            $file = self::normalizeFilePath($file, $basePath);

            Assertion::file(
                $file,
                sprintf(
                    '"%s" must contain a list of existing files. Could not find "%%s".',
                    $key
                )
            );

            return new SplFileInfo($file);
        };

        return array_map($normalizePath, $files);
    }

    /**
     * @param stdClass $raw
     * @param string   $key             Config property name
     * @param string   $basePath
     * @param Closure  $blacklistFilter
     *
     * @return iterable|SplFileInfo[]
     */
    private static function retrieveDirectories(stdClass $raw, string $key, string $basePath, Closure $blacklistFilter): iterable
    {
        $directories = self::retrieveDirectoryPaths($raw, $key, $basePath);

        if ([] !== $directories) {
            return Finder::create()
                ->files()
                ->filter($blacklistFilter)
                ->ignoreVCS(true)
                ->in($directories)
            ;
        }

        return [];
    }

    /**
     * @param stdClass $raw
     * @param string   $basePath
     * @param Closure  $blacklistFilter
     *
     * @return iterable[]|SplFileInfo[][]
     */
    private static function retrieveFilesFromFinders(stdClass $raw, string $key, string $basePath, Closure $blacklistFilter): array
    {
        if (isset($raw->{$key})) {
            return self::processFinders($raw->{$key}, $basePath, $blacklistFilter);
        }

        return [];
    }

    /**
     * @param array   $findersConfig   the configuration
     * @param string  $basePath
     * @param Closure $blacklistFilter
     *
     * @return Finder[]|SplFileInfo[][]
     */
    private static function processFinders(array $findersConfig, string $basePath, Closure $blacklistFilter): array
    {
        $processFinderConfig = function (stdClass $config) use ($basePath, $blacklistFilter) {
            return self::processFinder($config, $basePath, $blacklistFilter);
        };

        return array_map($processFinderConfig, $findersConfig);
    }

    /**
     * @param array   $findersConfig   the configuration
     * @param string  $basePath
     * @param Closure $blacklistFilter
     *
     * @return Finder
     */
    private static function processFinder(stdClass $config, string $basePath, Closure $blacklistFilter): Finder
    {
        $finder = Finder::create()
            ->files()
            ->filter($blacklistFilter)
            ->ignoreVCS(true)
        ;

        $normalizedConfig = (function (array $config, Finder $finder): array {
            $normalizedConfig = [];

            foreach ($config as $method => $arguments) {
                $method = trim($method);
                $arguments = (array) $arguments;

                Assertion::methodExists(
                    $method,
                    $finder,
                    'The method "Finder::%s" does not exist.'
                );

                $normalizedConfig[$method] = $arguments;
            }

            krsort($normalizedConfig);

            return $normalizedConfig;
        })((array) $config, $finder);

        $createNormalizedDirectories = function (string $directory) use ($basePath): string {
            $directory = self::normalizeDirectoryPath($directory, $basePath);

            Assertion::directory($directory);

            return $directory;
        };

        $normalizeFileOrDirectory = function (string &$fileOrDirectory) use ($basePath): void {
            $fileOrDirectory = self::normalizeDirectoryPath($fileOrDirectory, $basePath);

            if (false === file_exists($fileOrDirectory)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Path "%s" was expected to be a file or directory.',
                        $fileOrDirectory
                    )
                );
            }

            //TODO: add fileExists (as file or directory) to Assert
            if (false === is_file($fileOrDirectory)) {
                Assertion::directory($fileOrDirectory);
            } else {
                Assertion::file($fileOrDirectory);
            }
        };

        foreach ($normalizedConfig as $method => $arguments) {
            if ('in' === $method) {
                $normalizedConfig[$method] = $arguments = array_map($createNormalizedDirectories, $arguments);
            }

            if ('exclude' === $method) {
                $arguments = array_unique(array_map('trim', $arguments));
            }

            if ('append' === $method) {
                array_walk($arguments, $normalizeFileOrDirectory);

                $arguments = [$arguments];
            }

            foreach ($arguments as $argument) {
                $finder->$method($argument);
            }
        }

        return $finder;
    }

    /**
     * @param stdClass $raw
     * @param string   $key      Config property name
     * @param string   $basePath
     *
     * @return string[]
     */
    private static function retrieveDirectoryPaths(stdClass $raw, string $key, string $basePath): array
    {
        if (false === isset($raw->{$key})) {
            return [];
        }

        $directories = $raw->{$key};

        $normalizeDirectory = function (string $directory) use ($basePath, $key): string {
            $directory = self::normalizeDirectoryPath($directory, $basePath);

            Assertion::directory(
                $directory,
                sprintf(
                    '"%s" must contain a list of existing directories. Could not find "%%s".',
                    $key
                )
            );

            return $directory;
        };

        return array_map($normalizeDirectory, $directories);
    }

    private static function normalizeFilePath(string $file, string $basePath): string
    {
        $file = trim($file);

        if (false === is_absolute_path($file)) {
            $file = make_path_absolute($file, $basePath);
        }

        return $file;
    }

    private static function normalizeDirectoryPath(string $directory, string $basePath): string
    {
        $directory = trim($directory);

        if (false === is_absolute_path($directory)) {
            $directory = sprintf(
                '%s%s',
                $basePath.DIRECTORY_SEPARATOR,
                rtrim(
                    canonicalize($directory),
                    DIRECTORY_SEPARATOR
                )
            );
        }

        return $directory;
    }

    private static function retrieveBootstrapFile(stdClass $raw, string $basePath): ?string
    {
        // TODO: deprecate its usage & document this BC break. Compactors will not be configurable
        // through that extension point so this is pretty much useless unless proven otherwise.
        if (false === isset($raw->bootstrap)) {
            return null;
        }

        $file = $raw->bootstrap;

        if (false === is_absolute_path($file)) {
            $file = canonicalize(make_path_absolute($file, $basePath));
        }

        Assertion::file($file, 'The bootstrap path "%s" is not a file or does not exist.');

        return $file;
    }

    /**
     * @return Compactor[]
     */
    private static function retrieveCompactors(stdClass $raw): array
    {
        // TODO: only accept arrays when set unlike the doc says (it allows a string).
        if (false === isset($raw->compactors)) {
            return [];
        }

        $compactors = [];

        foreach ((array) $raw->compactors as $class) {
            Assertion::classExists($class, 'The compactor class "%s" does not exist.');
            Assertion::implementsInterface($class, Compactor::class, 'The class "%s" is not a compactor class.');

            if (Php::class === $class || LegacyPhp::class === $class) {
                $compactor = self::createPhpCompactor($raw);
            } else {
                $compactor = new $class();
            }

            $compactors[] = $compactor;
        }

        return $compactors;
    }

    private static function retrieveCompressionAlgorithm(stdClass $raw): ?int
    {
        // TODO: if in dev mode (when added), do not comment about the compression.
        // If not, add a warning to notify the user if no compression algorithm is used
        // provided the PHAR is not configured for web purposes.
        // If configured for the web, add a warning when a compression algorithm is used
        // as this can result in an overhead. Add a doc link explaining this.
        //
        // Unlike the doc: do not accept integers and document this BC break.
        if (false === isset($raw->compression)) {
            return null;
        }

        if (false === is_string($raw->compression)) {
            Assertion::integer(
                $raw->compression,
                'Expected compression to be an algorithm name, found %s instead.'
            );

            return $raw->compression;
        }

        $knownAlgorithmNames = array_keys(get_phar_compression_algorithms());

        Assertion::inArray(
            $raw->compression,
            $knownAlgorithmNames,
            sprintf(
                'Invalid compression algorithm "%%s", use one of "%s" instead.',
                implode('", "', $knownAlgorithmNames)
            )
        );

        $value = get_phar_compression_algorithms()[$raw->compression];

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
        // TODO: check if is used for the web as well when web is set to true
        // If that the case make this field mandatory otherwise adjust the check
        // rules accordinly to ensure we do not have an empty PHAR
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

        $contents = file_contents($mainScriptPath);

        // Remove the shebang line
        return preg_replace('/^#!.*\s*/', '', $contents);
    }

    /**
     * @return string[][]
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
                $processed[canonicalize(trim($match))] = canonicalize(trim($replace));
            }

            if (isset($processed['_empty_'])) {
                $processed[''] = $processed['_empty_'];

                unset($processed['_empty_']);
            }

            $map[] = $processed;
        }

        return $map;
    }

    /**
     * @return mixed
     */
    private static function retrieveMetadata(stdClass $raw)
    {
        // TODO: the doc currently say this can be any value; check if true
        // and if not add checks accordingly
        //
        // Also review the doc as I don't find it very helpful...
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
        // TODO: this parameter is not clear to me: review usage, doc & checks
        if (isset($raw->mimetypes)) {
            return (array) $raw->mimetypes;
        }

        return [];
    }

    private static function retrieveMungVariables(stdClass $raw): array
    {
        // TODO: this parameter is not clear to me: review usage, doc & checks
        // TODO: add error/warning if used when web is not enabled
        if (isset($raw->mung)) {
            return (array) $raw->mung;
        }

        return [];
    }

    private static function retrieveNotFoundScriptPath(stdClass $raw): ?string
    {
        // TODO: this parameter is not clear to me: review usage, doc & checks
        // TODO: add error/warning if used when web is not enabled
        if (isset($raw->{'not-found'})) {
            return $raw->{'not-found'};
        }

        return null;
    }

    private static function retrieveOutputPath(stdClass $raw, string $file): string
    {
        // TODO: make this path relative to the base path like everything else
        // otherwise this is really confusing. This is a BC break that needs to be
        // documented though (and update the doc accordingly as well)
        $base = getcwd().DIRECTORY_SEPARATOR;

        if (isset($raw->output)) {
            $path = $raw->output;

            if (false === is_absolute_path($path)) {
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
        // TODO: add check to not allow this setting without the private key path
        if (isset($raw->{'key-pass'})
            && is_string($raw->{'key-pass'})
        ) {
            return $raw->{'key-pass'};
        }

        return null;
    }

    private static function retrievePrivateKeyPath(stdClass $raw): ?string
    {
        // TODO: If passed need to check its existence
        // Also need

        if (isset($raw->key)) {
            return $raw->key;
        }

        return null;
    }

    private static function retrieveReplacements(stdClass $raw): array
    {
        // TODO: add exmample in the doc
        // Add checks against the values
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
        // TODO: check if is still relevant as IMO we are better off using OcramiusVersionPackage
        // to avoid messing around with that

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
        // TODO: double check why this is done and how it is used it's not completely clear to me.
        // Also make sure the documentation is up to date after.
        // Instead of having two sistinct doc entries for `datetime` and `datetime-format`, it would
        // be better to have only one element IMO like:
        //
        // "datetime": {
        //   "value": "val",
        //   "format": "Y-m-d"
        // }
        //
        // Also add a check that one cannot be provided without the other. Or maybe it should? I guess
        // if the datetime format is the default one it's ok; but in any case the format should not
        // be added without the datetime value...

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
        // TODO: unlike the doc says do not allow empty strings.
        // Leverage `Assertion` here?
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
        // TODO: trigger warning: if no signing algorithm is given provided we are not in dev mode
        // TODO: trigger a warning if the signing algorithm used is weak
        // TODO: no longer accept strings & document BC break
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
        // TODO: if provided check its existence here or should it be defered to later?
        // Works case this check can be duplicated...
        //
        // Check if is relative to base path: if not make it so (may be a BC break to document).
        // Once checked, a mention in the doc that this path is relative to base-path (unless
        // absolute).
        // Check that the path is not provided if a banner is already provided.
        if (isset($raw->{'banner-file'})) {
            return canonicalize($raw->{'banner-file'});
        }

        return null;
    }

    private static function retrieveStubBannerFromFile(string $basePath, ?string $stubBannerPath): ?string
    {
        // TODO: Add checks
        // TODO: The documentation is not clear enough IMO
        if (null == $stubBannerPath) {
            return null;
        }

        $stubBannerPath = make_path_absolute($stubBannerPath, $basePath);

        return file_contents($stubBannerPath);
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
        // TODO: look it up, really not clear to me neither is the doc
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
        // TODO: doc is not clear enough
        // Also check if is compatible web + CLI
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
        // TODO: false === not set; check & add test/doc
        $tokenizer = new Tokenizer();

        if (false === empty($raw->annotations) && isset($raw->annotations->ignore)) {
            $tokenizer->ignore(
                (array) $raw->annotations->ignore
            );
        }

        return new Php($tokenizer);
    }
}
