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

namespace KevinGH\Box\FileSystem;

use function array_reverse;
use function defined;
use const DIRECTORY_SEPARATOR;
use function error_get_last;
use function escapeshellarg;
use function exec;
use function file_exists;
use function file_get_contents;
use FilesystemIterator;
use function is_array;
use function is_dir;
use function is_link;
use function iterator_to_array;
use function random_int;
use function realpath;
use function rmdir;
use function sprintf;
use function str_replace;
use function strrpos;
use function substr;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Symfony\Component\Filesystem\Path;
use function sys_get_temp_dir;
use Traversable;
use function unlink;
use Webmozart\Assert\Assert;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Thomas Schulz <mail@king2500.net>
 *
 * @private
 */
final class FileSystem extends SymfonyFilesystem
{
    /**
     * Canonicalizes the given path.
     *
     * During normalization, all slashes are replaced by forward slashes ("/").
     * Furthermore, all "." and ".." segments are removed as far as possible.
     * ".." segments at the beginning of relative paths are not removed.
     *
     * ```php
     * echo Path::canonicalize("\webmozart\puli\..\css\style.css");
     * // => /webmozart/css/style.css
     *
     * echo Path::canonicalize("../css/./style.css");
     * // => ../css/style.css
     * ```
     *
     * This method is able to deal with both UNIX and Windows paths.
     *
     * @param string $path A path string
     *
     * @return string The canonical path
     */
    public function canonicalize(string $path): string
    {
        return Path::canonicalize($path);
    }

    /**
     * Normalizes the given path.
     *
     * During normalization, all slashes are replaced by forward slashes ("/").
     * Contrary to {@link canonicalize()}, this method does not remove invalid
     * or dot path segments. Consequently, it is much more efficient and should
     * be used whenever the given path is known to be a valid, absolute system
     * path.
     *
     * This method is able to deal with both UNIX and Windows paths.
     *
     * @param string $path a path string
     *
     * @return string the normalized path
     */
    public function normalize(string $path): string
    {
        return Path::normalize($path);
    }

    /**
     * Returns the directory part of the path.
     *
     * This method is similar to PHP's dirname(), but handles various cases
     * where dirname() returns a weird result:
     *
     *  - dirname() does not accept backslashes on UNIX
     *  - dirname("C:/webmozart") returns "C:", not "C:/"
     *  - dirname("C:/") returns ".", not "C:/"
     *  - dirname("C:") returns ".", not "C:/"
     *  - dirname("webmozart") returns ".", not ""
     *  - dirname() does not canonicalize the result
     *
     * This method fixes these shortcomings and behaves like dirname()
     * otherwise.
     *
     * The result is a canonical path.
     *
     * @param string $path a path string
     *
     * @return string The canonical directory part. Returns the root directory
     *                if the root directory is passed. Returns an empty string
     *                if a relative path is passed that contains no slashes.
     *                Returns an empty string if an empty string is passed.
     */
    public function getDirectory(string $path): string
    {
        return Path::getDirectory($path);
    }

    /**
     * Returns canonical path of the user's home directory.
     *
     * Supported operating systems:
     *
     *  - UNIX
     *  - Windows8 and upper
     *
     * If your operation system or environment isn't supported, an exception is thrown.
     *
     * The result is a canonical path.
     *
     * @return string The canonical home directory
     */
    public function getHomeDirectory(): string
    {
        return Path::getHomeDirectory();
    }

    /**
     * Returns the root directory of a path.
     *
     * The result is a canonical path.
     *
     * @param string $path a path string
     *
     * @return string The canonical root directory. Returns an empty string if
     *                the given path is relative or empty.
     */
    public function getRoot(string $path): string
    {
        return Path::getRoot($path);
    }

    /**
     * Returns the file name from a file path.
     *
     * @param string $path the path string
     *
     * @return string The file name
     */
    public function getFilename(string $path): string
    {
        return Path::getFilename($path);
    }

    /**
     * Returns the file name without the extension from a file path.
     *
     * @param string      $path      the path string
     * @param null|string $extension if specified, only that extension is cut
     *                               off (may contain leading dot)
     *
     * @return string the file name without extension
     */
    public function getFilenameWithoutExtension($path, $extension = null): string
    {
        return Path::getFilenameWithoutExtension($path, $extension);
    }

    /**
     * Returns the extension from a file path.
     *
     * @param string $path           the path string
     * @param bool   $forceLowerCase forces the extension to be lower-case
     *                               (requires mbstring extension for correct
     *                               multi-byte character handling in extension)
     *
     * @return string the extension of the file path (without leading dot)
     */
    public function getExtension(string $path, bool $forceLowerCase = false): string
    {
        return Path::getExtension($path, $forceLowerCase);
    }

    /**
     * Returns whether the path has an extension.
     *
     * @param string            $path       the path string
     * @param null|array|string $extensions if null or not provided, checks if
     *                                      an extension exists, otherwise
     *                                      checks for the specified extension
     *                                      or array of extensions (with or
     *                                      without leading dot)
     * @param bool              $ignoreCase whether to ignore case-sensitivity
     *                                      (requires mbstring extension for
     *                                      correct multi-byte character
     *                                      handling in the extension)
     *
     * @return bool returns `true` if the path has an (or the specified)
     *              extension and `false` otherwise
     */
    public function hasExtension(string $path, $extensions = null, bool $ignoreCase = false): bool
    {
        return Path::hasExtension($path, $extensions, $ignoreCase);
    }

    /**
     * Changes the extension of a path string.
     *
     * @param string $path      The path string with filename.ext to change.
     * @param string $extension new extension (with or without leading dot)
     *
     * @return string the path string with new file extension
     */
    public function changeExtension(string $path, string $extension): string
    {
        return Path::changeExtension($path, $extension);
    }

    /**
     * Returns whether a path is relative.
     *
     * @param string $path a path string
     *
     * @return bool returns true if the path is relative or empty, false if
     *              it is absolute
     */
    public function isRelativePath(string $path): bool
    {
        return !$this->isAbsolutePath($path);
    }

    /**
     * Turns a relative path into an absolute path.
     *
     * Usually, the relative path is appended to the given base path. Dot
     * segments ("." and "..") are removed/collapsed and all slashes turned
     * into forward slashes.
     *
     * ```php
     * echo Path::makeAbsolute("../style.css", "/webmozart/puli/css");
     * // => /webmozart/puli/style.css
     * ```
     *
     * If an absolute path is passed, that path is returned unless its root
     * directory is different than the one of the base path. In that case, an
     * exception is thrown.
     *
     * ```php
     * Path::makeAbsolute("/style.css", "/webmozart/puli/css");
     * // => /style.css
     *
     * Path::makeAbsolute("C:/style.css", "C:/webmozart/puli/css");
     * // => C:/style.css
     *
     * Path::makeAbsolute("C:/style.css", "/webmozart/puli/css");
     * // InvalidArgumentException
     * ```
     *
     * If the base path is not an absolute path, an exception is thrown.
     *
     * The result is a canonical path.
     *
     * @param string $path     a path to make absolute
     * @param string $basePath an absolute base path
     *
     * @return string an absolute path in canonical form
     */
    public function makeAbsolute(string $path, string $basePath): string
    {
        return Path::makeAbsolute($path, $basePath);
    }

    /**
     * Turns a path into a relative path.
     *
     * The relative path is created relative to the given base path:
     *
     * ```php
     * echo Path::makeRelative("/webmozart/style.css", "/webmozart/puli");
     * // => ../style.css
     * ```
     *
     * If a relative path is passed and the base path is absolute, the relative
     * path is returned unchanged:
     *
     * ```php
     * Path::makeRelative("style.css", "/webmozart/puli/css");
     * // => style.css
     * ```
     *
     * If both paths are relative, the relative path is created with the
     * assumption that both paths are relative to the same directory:
     *
     * ```php
     * Path::makeRelative("style.css", "webmozart/puli/css");
     * // => ../../../style.css
     * ```
     *
     * If both paths are absolute, their root directory must be the same,
     * otherwise an exception is thrown:
     *
     * ```php
     * Path::makeRelative("C:/webmozart/style.css", "/webmozart/puli");
     * // InvalidArgumentException
     * ```
     *
     * If the passed path is absolute, but the base path is not, an exception
     * is thrown as well:
     *
     * ```php
     * Path::makeRelative("/webmozart/style.css", "webmozart/puli");
     * // InvalidArgumentException
     * ```
     *
     * If the base path is not an absolute path, an exception is thrown.
     *
     * The result is a canonical path.
     *
     * @param string $path     a path to make relative
     * @param string $basePath a base path
     *
     * @return string a relative path in canonical form
     */
    public function makeRelative(string $path, string $basePath): string
    {
        return Path::makeRelative($path, $basePath);
    }

    /**
     * Returns whether the given path is on the local filesystem.
     *
     * @param string $path a path string
     *
     * @return bool returns true if the path is local, false for a URL
     */
    public function isLocal(string $path): bool
    {
        return Path::isLocal($path);
    }

    /**
     * Returns the longest common base path of a set of paths.
     *
     * Dot segments ("." and "..") are removed/collapsed and all slashes turned
     * into forward slashes.
     *
     * ```php
     * $basePath = Path::getLongestCommonBasePath(array(
     *     '/webmozart/css/style.css',
     *     '/webmozart/css/..'
     * ));
     * // => /webmozart
     * ```
     *
     * The root is returned if no common base path can be found:
     *
     * ```php
     * $basePath = Path::getLongestCommonBasePath(array(
     *     '/webmozart/css/style.css',
     *     '/puli/css/..'
     * ));
     * // => /
     * ```
     *
     * If the paths are located on different Windows partitions, `null` is
     * returned.
     *
     * ```php
     * $basePath = Path::getLongestCommonBasePath(array(
     *     'C:/webmozart/css/style.css',
     *     'D:/webmozart/css/..'
     * ));
     * // => null
     * ```
     *
     * @param array $paths a list of paths
     *
     * @return null|string the longest common base path in canonical form or
     *                     `null` if the paths are on different Windows
     *                     partitions
     */
    public function getLongestCommonBasePath(array $paths): ?string
    {
        return Path::getLongestCommonBasePath(...$paths);
    }

    /**
     * Joins two or more path strings.
     *
     * The result is a canonical path.
     *
     * @param string|string[] $paths path parts as parameters or array
     *
     * @return string the joint path
     */
    public function join(array|string $paths): string
    {
        return Path::join($paths);
    }

    /**
     * Returns whether a path is a base path of another path.
     *
     * Dot segments ("." and "..") are removed/collapsed and all slashes turned
     * into forward slashes.
     *
     * ```php
     * Path::isBasePath('/webmozart', '/webmozart/css');
     * // => true
     *
     * Path::isBasePath('/webmozart', '/webmozart');
     * // => true
     *
     * Path::isBasePath('/webmozart', '/webmozart/..');
     * // => false
     *
     * Path::isBasePath('/webmozart', '/puli');
     * // => false
     * ```
     *
     * @param string $basePath the base path to test
     * @param string $ofPath   the other path
     *
     * @return bool whether the base path is a base path of the other path
     */
    public function isBasePath(string $basePath, string $ofPath): bool
    {
        return Path::isBasePath($basePath, $ofPath);
    }

    public function escapePath(string $path): string
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Gets the contents of a file.
     *
     * @param string $file File path
     *
     * @throws IOException If the file cannot be read
     *
     * @return string File contents
     */
    public function getFileContents(string $file): string
    {
        Assert::file($file);
        Assert::readable($file);

        if (false === ($contents = @file_get_contents($file))) {
            throw new IOException(
                sprintf(
                    'Failed to read file "%s": %s.',
                    $file,
                    error_get_last()['message'],
                ),
                0,
                null,
                $file,
            );
        }

        return $contents;
    }

    /**
     * Creates a temporary directory.
     *
     * @param string $namespace the directory path in the system's temporary directory
     * @param string $className the name of the test class
     *
     * @return string the path to the created directory
     */
    public function makeTmpDir(string $namespace, string $className): string
    {
        if (false !== ($pos = strrpos($className, '\\'))) {
            $shortClass = substr($className, $pos + 1);
        } else {
            $shortClass = $className;
        }

        // Usage of realpath() is important if the temporary directory is a
        // symlink to another directory (e.g. /var => /private/var on some Macs)
        // We want to know the real path to avoid comparison failures with
        // code that uses real paths only
        $systemTempDir = str_replace('\\', '/', realpath(sys_get_temp_dir()));
        $basePath = $systemTempDir.'/'.$namespace.'/'.$shortClass;

        $result = false;
        $attempts = 0;

        do {
            $tmpDir = $this->escapePath($basePath.random_int(10000, 99999));

            try {
                $this->mkdir($tmpDir, 0777);

                $result = true;
            } catch (IOException) {
                ++$attempts;
            }
        } while (false === $result && $attempts <= 10);

        return $tmpDir;
    }

    /**
     * Removes files or directories.
     *
     * @param iterable|string $files A filename, an array of files, or a \Traversable instance to remove
     *
     * @throws IOException When removal fails
     */
    public function remove($files): void
    {
        if ($files instanceof Traversable) {
            $files = iterator_to_array($files, false);
        } elseif (!is_array($files)) {
            $files = [$files];
        }
        $files = array_reverse($files);
        foreach ($files as $file) {
            // MODIFIED CODE
            if (defined('PHP_WINDOWS_VERSION_BUILD') && is_dir($file)) {
                exec(sprintf('rd /s /q %s', escapeshellarg($file)));
            // - MODIFIED CODE
            } elseif (is_link($file)) {
                // See https://bugs.php.net/52176
                if (!@(unlink($file) || '\\' !== DIRECTORY_SEPARATOR || rmdir($file)) && file_exists($file)) {
                    $error = error_get_last();

                    throw new IOException(sprintf('Failed to remove symlink "%s": %s.', $file, $error['message']));
                }
            } elseif (is_dir($file)) {
                $this->remove(new FilesystemIterator($file, FilesystemIterator::CURRENT_AS_PATHNAME | FilesystemIterator::SKIP_DOTS));

                if (!@rmdir($file) && file_exists($file)) {
                    $error = error_get_last();

                    throw new IOException(sprintf('Failed to remove directory "%s": %s.', $file, $error['message']));
                }
            } elseif (!@unlink($file) && file_exists($file)) {
                $error = error_get_last();

                throw new IOException(sprintf('Failed to remove file "%s": %s.', $file, $error['message']));
            // MODIFIED CODE
            } elseif (file_exists($file)) {
                throw new IOException(sprintf('Failed to remove file "%s".', $file));
                // - MODIFIED CODE
            }
        }
    }
}
