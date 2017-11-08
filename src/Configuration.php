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
use Datetime;
use DateTimeImmutable;
use Herrera\Annotations\Tokenizer;
use Herrera\Box\Compactor\CompactorInterface;
use Herrera\Box\Compactor\Php;
use InvalidArgumentException;
use Phar;
use Phine\Path\Path;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * Manages the configuration settings.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Configuration
{
    private const UNSET = 'unset';

    /**
     * The configuration file path.
     *
     * @var string
     */
    private $file;

    /**
     * The raw configuration settings.
     *
     * @var object
     */
    private $raw;

    /**
     * @var null|string
     */
    private $_bootstrapFile = self::UNSET;

    /**
     * @var string
     */
    private $_outputPath;

    /**
     * @var array
     */
    private $_replacements;

    /**
     * Sets the raw configuration settings.
     *
     * @param string $file the configuration file path
     * @param object $raw  the raw settings
     */
    public function __construct($file, $raw)
    {
        $this->file = $file;
        $this->raw = $raw;
    }

    /**
     * Returns the Phar alias.
     *
     * @return string the alias
     */
    public function getAlias()
    {
        if (isset($this->raw->alias)) {
            return $this->raw->alias;
        }

        return 'default.phar';
    }

    /**
     * Returns the base path.
     *
     * @throws InvalidArgumentException if the base path is not valid
     *
     * @return string the base path
     */
    public function getBasePath()
    {
        if (isset($this->raw->{'base-path'})) {
            if (false === is_dir($this->raw->{'base-path'})) {
                throw new InvalidArgumentException(
                    sprintf(
                        'The base path "%s" is not a directory or does not exist.',
                        $this->raw->{'base-path'}
                    )
                );
            }

            return realpath($this->raw->{'base-path'});
        }

        return realpath(dirname($this->file));
    }

    /**
     * Returns the base path as a regular expression for trimming paths.
     *
     * @return string the regular expression
     */
    public function getBasePathRegex()
    {
        return '/'
             .preg_quote($this->getBasePath().DIRECTORY_SEPARATOR, '/')
             .'/';
    }

    /**
     * Returns the list of relative directory paths for binary files.
     *
     * @return array the list of paths
     */
    public function getBinaryDirectories()
    {
        if (isset($this->raw->{'directories-bin'})) {
            $directories = (array) $this->raw->{'directories-bin'};
            $base = $this->getBasePath();

            array_walk(
                $directories,
                function (&$directory) use ($base): void {
                    $directory = $base
                               .DIRECTORY_SEPARATOR
                               .Path::canonical($directory);
                }
            );

            return $directories;
        }

        return [];
    }

    /**
     * Returns the iterator for the binary directory paths.
     *
     * @return Finder the iterator
     */
    public function getBinaryDirectoriesIterator()
    {
        if ([] !== ($directories = $this->getBinaryDirectories())) {
            return Finder::create()
                    ->files()
                    ->filter($this->getBlacklistFilter())
                    ->ignoreVCS(true)
                    ->in($directories);
        }

        return null;
    }

    /**
     * Returns the list of relative paths for binary files.
     *
     * @return array the list of paths
     */
    public function getBinaryFiles()
    {
        if (isset($this->raw->{'files-bin'})) {
            $base = $this->getBasePath();
            $files = [];

            foreach ((array) $this->raw->{'files-bin'} as $file) {
                $files[] = new SplFileInfo(
                    $base.DIRECTORY_SEPARATOR.Path::canonical($file)
                );
            }

            return $files;
        }

        return [];
    }

    /**
     * Returns an iterator for the binary files.
     *
     * @return ArrayIterator the iterator
     */
    public function getBinaryFilesIterator()
    {
        if ([] !== ($files = $this->getBinaryFiles())) {
            return new ArrayIterator($files);
        }

        return null;
    }

    /**
     * Returns the list of configured Finder instances for binary files.
     *
     * @return Finder[] the list of Finders
     */
    public function getBinaryFinders()
    {
        if (isset($this->raw->{'finder-bin'})) {
            return $this->processFinders($this->raw->{'finder-bin'});
        }

        return [];
    }

    /**
     * Returns the list of blacklisted relative file paths.
     *
     * @return array the list of paths
     */
    public function getBlacklist()
    {
        if (isset($this->raw->blacklist)) {
            $blacklist = (array) $this->raw->blacklist;

            array_walk(
                $blacklist,
                function (&$file): void {
                    $file = Path::canonical($file);
                }
            );

            return $blacklist;
        }

        return [];
    }

    /**
     * Returns a filter callable for the configured blacklist.
     *
     * @return callable the callable
     */
    public function getBlacklistFilter()
    {
        $blacklist = $this->getBlacklist();
        $base = '/^'
              .preg_quote($this->getBasePath().DIRECTORY_SEPARATOR, '/')
              .'/';

        return function (SplFileInfo $file) use ($base, $blacklist) {
            $path = Path::canonical(
                preg_replace($base, '', $file->getPathname())
            );

            if (in_array($path, $blacklist, true)) {
                return false;
            }

            return null;
        };
    }

    public function getBootstrapFile(): ?string
    {
        if (self::UNSET !== $this->_bootstrapFile) {
            return $this->_bootstrapFile;
        }

        if (false === isset($this->raw->bootstrap)) {
            $path = null;
        } else {
            $path = $this->raw->bootstrap;

            if (false === Path::isAbsolute($path)) {
                $path = Path::canonical(
                    $this->getBasePath().DIRECTORY_SEPARATOR.$path
                );
            }
        }

        $this->_bootstrapFile = $path;

        return $path;
    }

    /**
     * Loads the configured bootstrap file if available.
     */
    public function loadBootstrap(): void
    {
        $file = $this->getBootstrapFile();

        if (null === $file) {
            return;
        }

        if (false === file_exists($file)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The bootstrap path "%s" is not a file or does not exist.',
                    $file
                )
            );
        }

        include $file;
    }

    /**
     * Returns the list of file contents compactors.
     *
     * @throws InvalidArgumentException if a class is not valid
     *
     * @return CompactorInterface[] the list of compactors
     */
    public function getCompactors()
    {
        $compactors = [];

        if (isset($this->raw->compactors)) {
            foreach ((array) $this->raw->compactors as $class) {
                if (false === class_exists($class)) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'The compactor class "%s" does not exist.',
                            $class
                        )
                    );
                }

                $compactor = new $class();

                if (false === ($compactor instanceof CompactorInterface)) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'The class "%s" is not a compactor class.',
                            $class
                        )
                    );
                }

                if ($compactor instanceof Php) {
                    if (!empty($this->raw->annotations)) {
                        $tokenizer = new Tokenizer();

                        if (isset($this->raw->annotations->ignore)) {
                            $tokenizer->ignore(
                                (array) $this->raw->annotations->ignore
                            );
                        }

                        $compactor->setTokenizer($tokenizer);
                    }
                }

                $compactors[] = $compactor;
            }
        }

        return $compactors;
    }

    /**
     * Returns the Phar compression algorithm.
     *
     * @throws InvalidArgumentException if the algorithm is not valid
     *
     * @return int the compression algorithm
     */
    public function getCompressionAlgorithm()
    {
        if (isset($this->raw->compression)) {
            if (is_string($this->raw->compression)) {
                if (false === defined('Phar::'.$this->raw->compression)) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'The compression algorithm "%s" is not supported.',
                            $this->raw->compression
                        )
                    );
                }

                $value = constant('Phar::'.$this->raw->compression);

                // Phar::NONE is not valid for compressFiles()
                if (Phar::NONE === $value) {
                    return null;
                }

                return $value;
            }

            return $this->raw->compression;
        }

        return null;
    }

    /**
     * Returns the list of relative directory paths.
     *
     * @return array the list of paths
     */
    public function getDirectories()
    {
        if (isset($this->raw->directories)) {
            $directories = (array) $this->raw->directories;
            $base = $this->getBasePath();

            array_walk(
                $directories,
                function (&$directory) use ($base): void {
                    $directory = $base
                               .DIRECTORY_SEPARATOR
                               .rtrim(Path::canonical($directory), DIRECTORY_SEPARATOR);
                }
            );

            return $directories;
        }

        return [];
    }

    /**
     * Returns the iterator for the directory paths.
     *
     * @return Finder the iterator
     */
    public function getDirectoriesIterator()
    {
        if ([] !== ($directories = $this->getDirectories())) {
            return Finder::create()
                    ->files()
                    ->filter($this->getBlacklistFilter())
                    ->ignoreVCS(true)
                    ->in($directories);
        }

        return null;
    }

    /**
     * Returns the file mode in octal form.
     *
     * @return int the file mode
     */
    public function getFileMode()
    {
        if (isset($this->raw->chmod)) {
            return intval($this->raw->chmod, 8);
        }

        return null;
    }

    /**
     * Returns the list of relative file paths.
     *
     * @throws RuntimeException if one of the files does not exist
     *
     * @return array the list of paths
     */
    public function getFiles()
    {
        if (isset($this->raw->files)) {
            $base = $this->getBasePath();
            $files = [];

            foreach ((array) $this->raw->files as $file) {
                $file = new SplFileInfo(
                    $path = $base.DIRECTORY_SEPARATOR.Path::canonical($file)
                );

                if (false === $file->isFile()) {
                    throw new RuntimeException(
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

        return [];
    }

    /**
     * Returns an iterator for the files.
     *
     * @return ArrayIterator the iterator
     */
    public function getFilesIterator()
    {
        if ([] !== ($files = $this->getFiles())) {
            return new ArrayIterator($files);
        }

        return null;
    }

    /**
     * Returns the list of configured Finder instances.
     *
     * @return Finder[] the list of Finders
     */
    public function getFinders()
    {
        if (isset($this->raw->finder)) {
            return $this->processFinders($this->raw->finder);
        }

        return [];
    }

    public function getDatetimeFormat()
    {
        if (isset($this->raw->{'datetime_format'})) {
            return $this->raw->{'datetime_format'};
        }

        return 'Y-m-d H:i:s';
    }

    /**
     * Returns the Git tag name or short commit hash.
     *
     * @throws RuntimeException if the version could not be retrieved
     *
     * @return string the tag name or short commit hash
     *
     * @deprecated should be made private
     */
    public function getGitVersion(): string
    {
        try {
            return $this->getGitTag();
        } catch (RuntimeException $exception) {
            try {
                return $this->getGitHash(true);
            } catch (RuntimeException $exception) {
                throw new RuntimeException(
                    sprintf(
                        'The tag or commit hash could not be retrieved from "%s": %s',
                        dirname($this->file),
                        $exception->getMessage()
                    ),
                    0,
                    $exception
                );
            }
        }
    }

    /**
     * Returns the processed contents of the main script file.
     *
     * @throws RuntimeException if the file could not be read
     *
     * @return string the contents
     */
    public function getMainScriptContents()
    {
        if (null !== ($path = $this->getMainScriptPath())) {
            $path = $this->getBasePath().DIRECTORY_SEPARATOR.$path;

            if (false === ($contents = @file_get_contents($path))) {
                $errors = error_get_last();
                if (null === $errors) {
                    $errors = ['message' => 'failed to get contents of \''.$path.'\''];
                }

                throw new RuntimeException($errors['message']);
            }

            return preg_replace('/^#!.*\s*/', '', $contents);
        }

        return null;
    }

    /**
     * Returns the main script file path.
     *
     * @return string the file path
     */
    public function getMainScriptPath(): ?string
    {
        if (isset($this->raw->main)) {
            return Path::canonical($this->raw->main);
        }

        return null;
    }

    /**
     * Returns the internal file path mapping.
     *
     * @return array the mapping
     */
    public function getMap()
    {
        if (isset($this->raw->map)) {
            $map = [];

            foreach ((array) $this->raw->map as $item) {
                $processed = [];

                foreach ($item as $match => $replace) {
                    $processed[Path::canonical($match)] = Path::canonical($replace);
                }

                if (isset($processed['_empty_'])) {
                    $processed[''] = $processed['_empty_'];

                    unset($processed['_empty_']);
                }

                $map[] = $processed;
            }

            return $map;
        }

        return [];
    }

    /**
     * Returns a mapping callable for the configured map.
     *
     * @return callable the mapping callable
     */
    public function getMapper()
    {
        $map = $this->getMap();

        return function ($path) use ($map) {
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
     * Returns the Phar metadata.
     *
     * @return mixed the metadata
     */
    public function getMetadata()
    {
        if (isset($this->raw->metadata)) {
            if (is_object($this->raw->metadata)) {
                return (array) $this->raw->metadata;
            }

            return $this->raw->metadata;
        }

        return null;
    }

    /**
     * Returns the file extension MIME type mapping.
     *
     * @return array the mapping
     */
    public function getMimetypeMapping()
    {
        if (isset($this->raw->mimetypes)) {
            return (array) $this->raw->mimetypes;
        }

        return [];
    }

    /**
     * Returns the list of server variables to modify for execution.
     *
     * @return array the list of variables
     */
    public function getMungVariables()
    {
        if (isset($this->raw->mung)) {
            return (array) $this->raw->mung;
        }

        return [];
    }

    /**
     * Returns the file path to the script to execute when a file is not found.
     *
     * @return string the file path
     */
    public function getNotFoundScriptPath()
    {
        if (isset($this->raw->{'not-found'})) {
            return $this->raw->{'not-found'};
        }

        return null;
    }

    public function getOutputPath(): string
    {
        if (null !== $this->_outputPath) {
            return $this->_outputPath;
        }

        $base = getcwd().DIRECTORY_SEPARATOR;

        if (isset($this->raw->output)) {
            $path = $this->raw->output;

            if (false === Path::isAbsolute($path)) {
                $path = Path::canonical($base.$path);
            }
        } else {
            $path = $base.'default.phar';
        }

        if (false !== strpos($path, '@'.'git-version@')) {
            $path = str_replace('@'.'git-version@', $this->getGitVersion(), $path);
        }

        $this->_outputPath = $path;

        return $path;
    }

    /**
     * Returns the private key passphrase.
     *
     * @return string the passphrase
     */
    public function getPrivateKeyPassphrase()
    {
        if (isset($this->raw->{'key-pass'})
            && is_string($this->raw->{'key-pass'})) {
            return $this->raw->{'key-pass'};
        }

        return null;
    }

    /**
     * Returns the private key file path.
     *
     * @return string the file path
     */
    public function getPrivateKeyPath()
    {
        if (isset($this->raw->key)) {
            return $this->raw->key;
        }

        return null;
    }

    /**
     * Returns the processed list of replacement placeholders and their values.
     *
     * @return array the list of replacements
     */
    public function getProcessedReplacements()
    {
        if (null !== $this->_replacements) {
            return $this->_replacements;
        }

        $values = $this->getReplacements();

        if (null !== ($git = $this->getGitHashPlaceholder())) {
            $values[$git] = $this->getGitHash();
        }

        if (null !== ($git = $this->getGitShortHashPlaceholder())) {
            $values[$git] = $this->getGitHash(true);
        }

        if (null !== ($git = $this->getGitTagPlaceholder())) {
            $values[$git] = $this->getGitTag();
        }

        if (null !== ($git = $this->getGitVersionPlaceholder())) {
            $values[$git] = $this->getGitVersion();
        }

        if (null !== ($date = $this->getDatetimeNowPlaceHolder())) {
            $values[$date] = $this->getDatetimeNow($this->getDatetimeFormat());
        }

        $sigil = $this->getReplacementSigil();

        foreach ($values as $key => $value) {
            unset($values[$key]);

            $values["$sigil$key$sigil"] = $value;
        }

        $this->_replacements = $values;

        return $this->_replacements;
    }

    /**
     * Returns the list of replacement placeholders and their values.
     *
     * @return array the list of replacements
     */
    public function getReplacements()
    {
        if (isset($this->raw->replacements)) {
            return (array) $this->raw->replacements;
        }

        return [];
    }

    /**
     * Returns the shebang line.
     *
     * @throws InvalidArgumentException if the shebang line is no valid
     *
     * @return string the shebang line
     */
    public function getShebang()
    {
        if (isset($this->raw->shebang)) {
            if (('' === $this->raw->shebang) || (false === $this->raw->shebang)) {
                return '';
            }

            $shebang = trim($this->raw->shebang);

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

        return null;
    }

    /**
     * Returns the Phar signing algorithm.
     *
     * @throws InvalidArgumentException if the algorithm is not valid
     *
     * @return int the signing algorithm
     */
    public function getSigningAlgorithm()
    {
        if (isset($this->raw->algorithm)) {
            if (is_string($this->raw->algorithm)) {
                if (false === defined('Phar::'.$this->raw->algorithm)) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'The signing algorithm "%s" is not supported.',
                            $this->raw->algorithm
                        )
                    );
                }

                return constant('Phar::'.$this->raw->algorithm);
            }

            return $this->raw->algorithm;
        }

        return Phar::SHA1;
    }

    /**
     * Returns the stub banner comment.
     *
     * @return string the stub banner comment
     */
    public function getStubBanner()
    {
        if (isset($this->raw->{'banner'})) {
            return $this->raw->{'banner'};
        }

        return null;
    }

    /**
     * Returns the stub banner comment from the file.
     *
     * @throws RuntimeException if the comment file could not be read
     *
     * @return string the stub banner comment
     */
    public function getStubBannerFromFile()
    {
        if (null !== ($path = $this->getStubBannerPath())) {
            $path = $this->getBasePath().DIRECTORY_SEPARATOR.$path;

            if (false === ($contents = @file_get_contents($path))) {
                $errors = error_get_last();
                if (null === $errors) {
                    $errors = ['message' => 'failed to get contents of \''.$path.'\''];
                }

                throw new RuntimeException($errors['message']);
            }

            return $contents;
        }

        return null;
    }

    /**
     * Returns the path to the stub banner comment file.
     *
     * @return string the stub header comment file path
     */
    public function getStubBannerPath()
    {
        if (isset($this->raw->{'banner-file'})) {
            return Path::canonical($this->raw->{'banner-file'});
        }

        return null;
    }

    /**
     * Returns the Phar stub file path.
     *
     * @return string the file path
     */
    public function getStubPath()
    {
        if (isset($this->raw->stub) && is_string($this->raw->stub)) {
            return $this->raw->stub;
        }

        return null;
    }

    /**
     * Checks if StubGenerator->extract() should be used.
     *
     * @return bool TRUE if it should be used, FALSE if not
     */
    public function isExtractable()
    {
        if (isset($this->raw->extract)) {
            return $this->raw->extract;
        }

        return false;
    }

    /**
     * Checks if Phar::interceptFileFuncs() should be used.
     *
     * @return bool TRUE if it should be used, FALSE if not
     */
    public function isInterceptFileFuncs()
    {
        if (isset($this->raw->intercept)) {
            return $this->raw->intercept;
        }

        return false;
    }

    /**
     * Checks if the user should be prompted for the private key passphrase.
     *
     * @return bool TRUE if they should be prompted, FALSE if not
     */
    public function isPrivateKeyPrompt()
    {
        if (isset($this->raw->{'key-pass'})
            && (true === $this->raw->{'key-pass'})) {
            return true;
        }

        return false;
    }

    /**
     * Checks if the Phar stub should be generated.
     *
     * @return bool TRUE if it should be generated, FALSE if not
     */
    public function isStubGenerated()
    {
        if (isset($this->raw->stub) && (true === $this->raw->stub)) {
            return true;
        }

        return false;
    }

    /**
     * Checks if the Phar is going to be used for the web.
     *
     * @return bool TRUE if it will be, FALSE if not
     */
    public function isWebPhar()
    {
        if (isset($this->raw->web)) {
            return $this->raw->web;
        }

        return false;
    }

    private function getDatetimeNow($format)
    {
        $now = new DateTimeImmutable('now');
        $datetime = $now->format($format);

        if (!$datetime) {
            throw new RuntimeException("'$format' is not a valid PHP date format");
        }

        return $datetime;
    }

    private function getDatetimeNowPlaceHolder()
    {
        if (isset($this->raw->{'datetime'})) {
            return $this->raw->{'datetime'};
        }

        return null;
    }

    /**
     * Returns the Git commit hash.
     *
     * @param bool $short Use the short version?
     *
     * @return string the commit hash
     */
    private function getGitHash($short = false): string
    {
        return $this->runGitCommand(
            sprintf(
                'git log --pretty="%s" -n1 HEAD',
                $short ? '%h' : '%H'
            )
        );
    }

    /**
     * Returns the Git commit hash placeholder.
     *
     * @return string the placeholder
     */
    private function getGitShortHashPlaceholder(): ?string
    {
        if (isset($this->raw->{'git-commit-short'})) {
            return $this->raw->{'git-commit-short'};
        }

        return null;
    }

    /**
     * Returns the Git commit hash placeholder.
     *
     * @return string the placeholder
     */
    private function getGitHashPlaceholder(): ?string
    {
        if (isset($this->raw->{'git-commit'})) {
            return $this->raw->{'git-commit'};
        }

        return null;
    }

    /**
     * Returns the most recent Git tag.
     *
     * @return string the tag
     */
    private function getGitTag(): string
    {
        return $this->runGitCommand('git describe --tags HEAD');
    }

    /**
     * Returns the Git tag placeholder.
     *
     * @return string the placeholder
     */
    private function getGitTagPlaceholder(): ?string
    {
        if (isset($this->raw->{'git-tag'})) {
            return $this->raw->{'git-tag'};
        }

        return null;
    }

    /**
     * Returns the Git version placeholder.
     *
     * @return string the placeholder
     */
    private function getGitVersionPlaceholder(): ?string
    {
        if (isset($this->raw->{'git-version'})) {
            return $this->raw->{'git-version'};
        }

        return null;
    }

    /**
     * Returns the replacement placeholder sigil.
     *
     * @return string the placeholder sigil
     */
    private function getReplacementSigil(): string
    {
        if (isset($this->raw->{'replacement-sigil'})) {
            return $this->raw->{'replacement-sigil'};
        }

        return '@';
    }

    /**
     * Processes the Finders configuration list.
     *
     * @param array $config the configuration
     *
     * @throws InvalidArgumentException if the configured method does not exist
     *
     * @return Finder[] the list of Finders
     */
    private function processFinders(array $config)
    {
        $finders = [];
        $filter = $this->getBlacklistFilter();

        foreach ($config as $methods) {
            $finder = Finder::create()
                        ->files()
                        ->filter($filter)
                        ->ignoreVCS(true);

            if (isset($methods->in)) {
                $base = $this->getBasePath();
                $methods->in = (array) $methods->in;

                array_walk(
                    $methods->in,
                    function (&$directory) use ($base): void {
                        $directory = Path::canonical(
                            $base.DIRECTORY_SEPARATOR.$directory
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

            $finders[] = $finder;
        }

        return $finders;
    }

    /**
     * Runs a Git command on the repository.
     *
     * @param string $command the command
     *
     * @throws RuntimeException if the command failed
     *
     * @return string the trimmed output from the command
     */
    private function runGitCommand($command)
    {
        $path = dirname($this->file);
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
}
