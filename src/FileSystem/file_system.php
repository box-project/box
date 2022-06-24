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

use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Path;
use Traversable;

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
 *
 * @private
 */
function canonicalize(string $path): string
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->canonicalize($path);
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
 *
 * @private
 */
function normalize(string $path): string
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->normalize($path);
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
 *
 * @private
 */
function directory(string $path): string
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->getDirectory($path);
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
 *
 * @private
 */
function home_directory(): string
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->getHomeDirectory();
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
 *
 * @private
 */
function root(string $path): string
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->getRoot($path);
}

/**
 * Returns the file name from a file path.
 *
 * @param string $path the path string
 *
 * @return string The file name
 *
 * @private
 */
function filename(string $path): string
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->getFilename($path);
}

/**
 * Returns the file name without the extension from a file path.
 *
 * @param string      $path      the path string
 * @param null|string $extension if specified, only that extension is cut
 *                               off (may contain leading dot)
 *
 * @return string the file name without extension
 *
 * @private
 */
function filename_without_extension($path, $extension = null): string
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->getFilenameWithoutExtension($path, $extension);
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
 *
 * @private
 */
function extension(string $path, bool $forceLowerCase = false): string
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->getExtension($path, $forceLowerCase);
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
 *
 * @private
 */
function has_extension(string $path, $extensions = null, bool $ignoreCase = false): bool
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->hasExtension($path, $extensions, $ignoreCase);
}

/**
 * Changes the extension of a path string.
 *
 * @param string $path      The path string with filename.ext to change.
 * @param string $extension new extension (with or without leading dot)
 *
 * @return string the path string with new file extension
 *
 * @private
 */
function change_extension(string $path, string $extension): string
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->changeExtension($path, $extension);
}

/**
 * Returns whether the file path is an absolute path.
 *
 * @param string $path a path string
 *
 * @private
 */
function is_absolute_path(string $path): bool
{
    return Path::isAbsolute($path);
}

/**
 * Returns whether a path is relative.
 *
 * @param string $path a path string
 *
 * @return bool returns true if the path is relative or empty, false if
 *              it is absolute
 *
 * @private
 */
function is_relative_path(string $path): bool
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->isRelativePath($path);
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
 *
 * @private
 */
function make_path_absolute(string $path, string $basePath): string
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->makeAbsolute($path, $basePath);
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
 *
 * @private
 */
function make_path_relative(string $path, string $basePath): string
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->makeRelative($path, $basePath);
}

/**
 * Returns whether the given path is on the local filesystem.
 *
 * @param string $path a path string
 *
 * @return bool returns true if the path is local, false for a URL
 *
 * @private
 */
function is_local(string $path): bool
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->isLocal($path);
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
 *
 * @private
 */
function longest_common_base_path(array $paths): ?string
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->getLongestCommonBasePath($paths);
}

/**
 * Joins two or more path strings.
 *
 * The result is a canonical path.
 *
 * @param string|string[] $paths path parts as parameters or array
 *
 * @return string the joint path
 *
 * @private
 */
function join(array|string $paths): string
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->join($paths);
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
 *
 * @private
 */
function is_base_path(string $basePath, string $ofPath): bool
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->isBasePath($basePath, $ofPath);
}

function escape_path(string $path): string
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->escapePath($path);
}

/**
 * Gets the contents of a file.
 *
 * @param string $file File path
 *
 * @throws IOException If the file cannot be read
 *
 * @return string File contents
 *
 * @private
 */
function file_contents(string $file): string
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->getFileContents($file);
}

/**
 * Copies a file.
 *
 * If the target file is older than the origin file, it's always overwritten.
 * If the target file is newer, it is overwritten only when the
 * $overwriteNewerFiles option is set to true.
 *
 * @param string $originFile          The original filename
 * @param string $targetFile          The target filename
 * @param bool   $overwriteNewerFiles If true, target files newer than origin files are overwritten
 *
 * @throws FileNotFoundException When originFile doesn't exist
 * @throws IOException           When copy fails
 *
 * @private
 */
function copy(string $originFile, string $targetFile, bool $overwriteNewerFiles = false): void
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    $fileSystem->copy($originFile, $targetFile, $overwriteNewerFiles);
}

/**
 * Creates a directory recursively.
 *
 * @param iterable|string $dirs The directory path
 * @param int             $mode The directory mode
 *
 * @throws IOException On any directory creation failure
 *
 * @private
 */
function mkdir(iterable|string $dirs, int $mode = 0777): void
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    $fileSystem->mkdir($dirs, $mode);
}

/**
 * Removes files or directories.
 *
 * @param iterable|string $files A filename, an array of files, or a \Traversable instance to remove
 *
 * @throws IOException When removal fails
 *
 * @private
 */
function remove(iterable|string $files): void
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    $fileSystem->remove($files);
}

/**
 * Checks the existence of files or directories.
 *
 * @param iterable|string $files A filename, an array of files, or a \Traversable instance to check
 *
 * @return bool true if the file exists, false otherwise
 *
 * @private
 */
function exists(iterable|string $files): bool
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->exists($files);
}

/**
 * Sets access and modification time of file.
 *
 * @param iterable|string $files A filename, an array of files, or a \Traversable instance to create
 * @param int             $time  The touch time as a Unix timestamp
 * @param int             $atime The access time as a Unix timestamp
 *
 * @throws IOException When touch fails
 *
 * @private
 */
function touch(iterable|string $files, ?int $time = null, ?int $atime = null): void
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    $fileSystem->touch($files, $time, $atime);
}

/**
 * Change mode for an array of files or directories.
 *
 * @param iterable|string $files     A filename, an array of files, or a \Traversable instance to change mode
 * @param int             $mode      The new mode (octal)
 * @param int             $umask     The mode mask (octal)
 * @param bool            $recursive Whether change the mod recursively or not
 *
 * @throws IOException When the change fail
 *
 * @private
 */
function chmod(iterable|string $files, int $mode, int $umask = 0000, bool $recursive = false): void
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    $fileSystem->chmod($files, $mode, $umask, $recursive);
}

/**
 * Change the owner of an array of files or directories.
 *
 * @param iterable|string $files     A filename, an array of files, or a \Traversable instance to change owner
 * @param string          $user      The new owner user name
 * @param bool            $recursive Whether change the owner recursively or not
 *
 * @throws IOException When the change fail
 *
 * @private
 */
function chown(iterable|string $files, string $user, bool $recursive = false): void
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    $fileSystem->chown($files, $user, $recursive);
}

/**
 * Change the group of an array of files or directories.
 *
 * @param iterable|string $files     A filename, an array of files, or a \Traversable instance to change group
 * @param string          $group     The group name
 * @param bool            $recursive Whether change the group recursively or not
 *
 * @throws IOException When the change fail
 *
 * @private
 */
function chgrp(iterable|string $files, string $group, bool $recursive = false): void
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    $fileSystem->chgrp($files, $group, $recursive);
}

/**
 * Renames a file or a directory.
 *
 * @param string $origin    The origin filename or directory
 * @param string $target    The new filename or directory
 * @param bool   $overwrite Whether to overwrite the target if it already exists
 *
 * @throws IOException When target file or directory already exists
 * @throws IOException When origin cannot be renamed
 *
 * @private
 */
function rename(string $origin, string $target, bool $overwrite = false): void
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    $fileSystem->rename($origin, $target, $overwrite);
}

/**
 * Creates a symbolic link or copy a directory.
 *
 * @param string $originDir     The origin directory path
 * @param string $targetDir     The symbolic link name
 * @param bool   $copyOnWindows Whether to copy files if on Windows
 *
 * @throws IOException When symlink fails
 *
 * @private
 */
function symlink(string $originDir, string $targetDir, bool $copyOnWindows = false): void
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    $fileSystem->symlink($originDir, $targetDir, $copyOnWindows);
}

/**
 * Creates a hard link, or several hard links to a file.
 *
 * @param string          $originFile  The original file
 * @param string|string[] $targetFiles The target file(s)
 *
 * @throws FileNotFoundException When original file is missing or not a file
 * @throws IOException           When link fails, including if link already exists
 *
 * @private
 */
function hardlink(string $originFile, array|string $targetFiles): void
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    $fileSystem->hardlink($originFile, $targetFiles);
}

/**
 * Resolves links in paths.
 *
 * With $canonicalize = false (default)
 *      - if $path does not exist or is not a link, returns null
 *      - if $path is a link, returns the next direct target of the link without considering the existence of the target
 *
 * With $canonicalize = true
 *      - if $path does not exist, returns null
 *      - if $path exists, returns its absolute fully resolved final version
 *
 * @param string $path         A filesystem path
 * @param bool   $canonicalize Whether or not to return a canonicalized path
 *
 * @private
 */
function readlink(string $path, bool $canonicalize = false): ?string
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->readlink($path, $canonicalize);
}

/**
 * Mirrors a directory to another.
 *
 * @param string      $originDir The origin directory
 * @param string      $targetDir The target directory
 * @param Traversable $iterator  A Traversable instance
 * @param array       $options   An array of boolean options
 *                               Valid options are:
 *                               - $options['override'] Whether to override an existing file on copy or not (see copy())
 *                               - $options['copy_on_windows'] Whether to copy files instead of links on Windows (see symlink())
 *                               - $options['delete'] Whether to delete files that are not in the source directory (defaults to false)
 *
 * @throws IOException When file type is unknown
 *
 * @private
 */
function mirror(string $originDir, string $targetDir, ?Traversable $iterator = null, array $options = []): void
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    $fileSystem->mirror($originDir, $targetDir, $iterator, $options);
}

/**
 * Creates a temporary directory.
 *
 * @param string $namespace the directory path in the system's temporary directory
 * @param string $className the name of the test class
 *
 * @return string the path to the created directory
 *
 * @private
 */
function make_tmp_dir(string $namespace, string $className): string
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->makeTmpDir($namespace, $className);
}

/**
 * Creates a temporary file with support for custom stream wrappers.
 *
 * @param string $dir    The directory where the temporary filename will be created
 * @param string $prefix The prefix of the generated temporary filename
 *                       Note: Windows uses only the first three characters of prefix
 *
 * @return string The new temporary filename (with path), or throw an exception on failure
 *
 * @private
 */
function tempnam($dir, $prefix): string
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    return $fileSystem->tempnam($dir, $prefix);
}

/**
 * Atomically dumps content into a file.
 *
 * @param string      $filename The file to be written to
 * @param null|string $content  The data to write into the file
 *
 * @throws IOException if the file cannot be written to
 *
 * @private
 */
function dump_file(string $filename, ?string $content = null): void
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    $fileSystem->dumpFile($filename, $content);
}

/**
 * Appends content to an existing file.
 *
 * @param string $filename The file to which to append content
 * @param string $content  The content to append
 *
 * @throws IOException If the file is not writable
 *
 * @private
 */
function append_to_file(string $filename, string $content): void
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new FileSystem();
    }

    $fileSystem->appendToFile($filename, $content);
}
