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

use Assert\Assertion;

/*
 * The open-ended stub pattern.
 *
 * @var string
 */
define('BOX_EXTRACT_PATTERN_OPEN', '__HALT'."_COMPILER(); ?>\r\n");

/**
 * Extracts a PHAR without the extension.
 *
 * This class is a rewrite of the `Extract_Phar` class that is included
 * in the default stub for all phars. The class is designed to work from
 * inside and outside of a phar. Unlike the original class, the stub
 * length must be specified.
 *
 * @see https://github.com/php/php-src/blob/master/ext/phar/shortarc.php
 */
final class Box_Extract
{
    /**
     * @var string The open-ended stub pattern
     */
    private const PATTERN_OPEN = BOX_EXTRACT_PATTERN_OPEN;

    /**
     * @var int The gzip compression flag
     */
    private const GZ = 0x1000;

    /**
     * @var int The bzip2 compression flag
     */
    private const BZ2 = 0x2000;

    /**
     * @var int
     */
    private const MASK = 0x3000;

    /**
     * @var string The PHAR file path to extract
     */
    private $file;

    /**
     * @var resource The open file handle
     */
    private $handle;

    /**
     * @var int The length of the stub in the PHAR
     */
    private $stub;

    public function __construct(string $file, int $stubLength)
    {
        Assertion::file($file);

        $this->file = $file;
        $this->stub = $stubLength;
    }

    /**
     * Finds the phar's stub length using the end pattern.
     *
     * A "pattern" is a sequence of characters that indicate the end of a
     * stub, and the beginning of a manifest. This determines the complete
     * size of a stub, and is used as an offset to begin parsing the data
     * contained in the phar's manifest.
     *
     * The stub generated included with the Box library uses what I like
     * to call an open-ended pattern. This pattern uses the function
     * "__HALT_COMPILER();" at the end, with no following whitespace or
     * closing PHP tag. By default, this method will use that pattern,
     * defined as `Extract::PATTERN_OPEN`.
     *
     * The Phar class generates its own default stub. The pattern for the
     * default stub is slightly different than the one used by Box. This
     * pattern is defined as `Extract::PATTERN_DEFAULT`.
     *
     * If you have used your own custom stub, you will need to specify its
     * pattern as the `$pattern` argument, if you cannot use either of the
     * pattern constants defined.
     *
     * @param string $file    The PHAR file path
     * @param string $pattern The stub end pattern
     *
     * @return int The stub length
     */
    public static function findStubLength(
        string $file,
        string $pattern = self::PATTERN_OPEN
    ): int {
        Assertion::file($file);
        Assertion::readable($file);

        $fp = fopen($file, 'rb');

        $stub = null;
        $offset = 0;
        $combo = str_split($pattern);

        while (!feof($fp)) {
            if (fgetc($fp) === $combo[$offset]) {
                ++$offset;

                if (!isset($combo[$offset])) {
                    $stub = ftell($fp);

                    break;
                }
            } else {
                $offset = 0;
            }
        }

        fclose($fp);

        if (null === $stub) {
            throw new InvalidArgumentException(
                sprintf(
                    'The pattern could not be found in "%s".',
                    $file
                )
            );
        }

        return $stub;
    }

    /**
     * Extracts the PHAR to the directory path.
     *
     * If no directory path is given, a temporary one will be generated and
     * returned. If a directory path is given, the returned directory path
     * will be the same.
     *
     * @param string $dir The directory to extract to
     *
     * @return string The directory extracted to
     */
    public function go(string $dir = null): string
    {
        // Set up the output directory
        if (null === $dir) {
            $dir = rtrim(sys_get_temp_dir(), '\\/')
                .DIRECTORY_SEPARATOR
                .'pharextract'
                .DIRECTORY_SEPARATOR
                .basename($this->file, '.phar');
        } else {
            $dir = realpath($dir);
        }

        // Skip if already extracted
        $md5 = $dir.DIRECTORY_SEPARATOR.md5_file($this->file);

        if (file_exists($md5)) {
            return $dir;
        }

        if (!is_dir($dir)) {
            $this->createDir($dir);
        }

        // Open the file and skip stub
        $this->open();

        if (-1 === fseek($this->handle, $this->stub)) {
            throw new RuntimeException(
                sprintf(
                    'Could not seek to %d in the file "%s".',
                    $this->stub,
                    $this->file
                )
            );
        }

        // Read the manifest
        $info = $this->readManifest();

        if ($info['flags'] & self::GZ) {
            if (!function_exists('gzinflate')) {
                throw new RuntimeException(
                    'The zlib extension is (gzinflate()) is required for "%s.',
                    $this->file
                );
            }
        }

        if ($info['flags'] & self::BZ2) {
            if (!function_exists('bzdecompress')) {
                throw new RuntimeException(
                    'The bzip2 extension (bzdecompress()) is required for "%s".',
                    $this->file
                );
            }
        }

        self::purge($dir);

        $this->createDir($dir);
        $this->createFile($md5);

        foreach ($info['files'] as $info) {
            $path = $dir.DIRECTORY_SEPARATOR.$info['path'];
            $parent = dirname($path);

            if (!is_dir($parent)) {
                $this->createDir($parent);
            }

            if (preg_match('{/$}', $info['path'])) {
                $this->createDir($path, 0777, false);
            } else {
                $this->createFile(
                    $path,
                    $this->extractFile($info)
                );
            }
        }

        return $dir;
    }

    /**
     * Recursively deletes the directory or file path.
     *
     * @param string $path The path to delete
     */
    public static function purge(string $path): void
    {
        if (is_dir($path)) {
            foreach (scandir($path) as $item) {
                if (('.' === $item) || ('..' === $item)) {
                    continue;
                }

                self::purge($path.DIRECTORY_SEPARATOR.$item);
            }

            if (!rmdir($path)) {
                throw new RuntimeException(
                    sprintf(
                        'The directory "%s" could not be deleted.',
                        $path
                    )
                );
            }
        } else {
            if (!unlink($path)) {
                throw new RuntimeException(
                    sprintf(
                        'The file "%s" could not be deleted.',
                        $path
                    )
                );
            }
        }
    }

    /**
     * Creates a new directory.
     *
     * @param string $path      The directory path
     * @param int    $chmod     The file mode
     * @param bool   $recursive Recursively create path?
     *
     * @throws RuntimeException if the path could not be created
     */
    private function createDir(string $path, int $chmod = 0777, bool $recursive = true): void
    {
        if (!mkdir($path, $chmod, $recursive)) {
            throw new RuntimeException(
                sprintf(
                    'The directory path "%s" could not be created.',
                    $path
                )
            );
        }
    }

    /**
     * Creates a new file.
     *
     * @param string $path     The file path
     * @param string $contents The file contents
     * @param int    $mode     The file mode
     */
    private function createFile($path, $contents = '', $mode = 0666): void
    {
        if (false === file_put_contents($path, $contents)) {
            throw new RuntimeException(
                sprintf(
                    'The file "%s" could not be written.',
                    $path
                )
            );
        }

        if (!chmod($path, $mode)) {
            throw new RuntimeException(
                sprintf(
                    'The file "%s" could not be chmodded to %o.',
                    $path,
                    $mode
                )
            );
        }
    }

    /**
     * Extracts a single file from the PHAR.
     *
     * @param array $info The file information
     *
     * @return string The file data
     */
    private function extractFile(array $info): string
    {
        if (0 === $info['size']) {
            return '';
        }

        $data = $this->read($info['compressed_size']);

        if ($info['flags'] & self::GZ) {
            if (false === ($data = gzinflate($data))) {
                throw new RuntimeException(
                    sprintf(
                        'The "%s" file could not be inflated (gzip) from "%s".',
                        $info['path'],
                        $this->file
                    )
                );
            }
        } elseif ($info['flags'] & self::BZ2) {
            if (false === ($data = bzdecompress($data))) {
                throw new RuntimeException(
                    sprintf(
                        'The "%s" file could not be inflated (bzip2) from "%s".',
                        $info['path'],
                        $this->file
                    )
                );
            }
        }

        if (($actual = strlen($data)) !== $info['size']) {
            throw new UnexpectedValueException(
                sprintf(
                    'The size of "%s" (%d) did not match what was expected (%d) in "%s".',
                    $info['path'],
                    $actual,
                    $info['size'],
                    $this->file
                )
            );
        }

        $crc32 = sprintf('%u', crc32($data) & 0xffffffff);

        if ($info['crc32'] != $crc32) {
            throw new UnexpectedValueException(
                sprintf(
                    'The crc32 checksum (%s) for "%s" did not match what was expected (%s) in "%s".',
                    $crc32,
                    $info['path'],
                    $info['crc32'],
                    $this->file
                )
            );
        }

        return $data;
    }

    /**
     * Opens the file for reading.
     */
    private function open(): void
    {
        if (null === ($this->handle = fopen($this->file, 'rb'))) {
            $this->handle = null;

            throw new RuntimeException(
                sprintf(
                    'The file "%s" could not be opened for reading.',
                    $this->file
                )
            );
        }
    }

    /**
     * Reads the number of bytes from the file.
     *
     * @param int $bytes The number of bytes
     *
     * @return string The binary string read
     */
    private function read(int $bytes): string
    {
        $read = '';
        $total = $bytes;

        while (!feof($this->handle) && $bytes) {
            if (false === ($chunk = fread($this->handle, $bytes))) {
                throw new RuntimeException(
                    sprintf(
                        'Could not read %d bytes from "%s".',
                        $bytes,
                        $this->file
                    )
                );
            }

            $read .= $chunk;
            $bytes -= strlen($chunk);
        }

        if (($actual = strlen($read)) !== $total) {
            throw new RuntimeException(
                sprintf(
                    'Only read %d of %d in "%s".',
                    $actual,
                    $total,
                    $this->file
                )
            );
        }

        return $read;
    }

    /**
     * Reads and unpacks the manifest data from the phar.
     *
     * @return array the manifest
     */
    private function readManifest(): array
    {
        $size = unpack('V', $this->read(4));
        $size = $size[1];

        $raw = $this->read($size);

        // ++ start skip: API version, global flags, alias, and metadata
        $count = unpack('V', substr($raw, 0, 4));
        $count = $count[1];

        $aliasSize = unpack('V', substr($raw, 10, 4));
        $aliasSize = $aliasSize[1];
        $raw = substr($raw, 14 + $aliasSize);

        $metaSize = unpack('V', substr($raw, 0, 4));
        $metaSize = $metaSize[1];

        $offset = 0;
        $start = 4 + $metaSize;
        // -- end skip

        $manifest = [
            'files' => [],
            'flags' => 0,
        ];

        for ($i = 0; $i < $count; ++$i) {
            $length = unpack('V', substr($raw, $start, 4));
            $length = $length[1];
            $start += 4;

            $path = substr($raw, $start, $length);
            $start += $length;

            $file = unpack(
                'Vsize/Vtimestamp/Vcompressed_size/Vcrc32/Vflags/Vmetadata_length',
                substr($raw, $start, 24)
            );

            $file['path'] = $path;
            $file['crc32'] = sprintf('%u', $file['crc32'] & 0xffffffff);
            $file['offset'] = $offset;

            $offset += $file['compressed_size'];
            $start += 24 + $file['metadata_length'];

            $manifest['flags'] |= $file['flags'] & self::MASK;

            $manifest['files'][] = $file;
        }

        return $manifest;
    }
}
