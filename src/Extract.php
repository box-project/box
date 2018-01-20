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

use InvalidArgumentException;
use LengthException;
use RuntimeException;
use UnexpectedValueException;

/*
 * The default stub pattern.
 *
 * @var string
 */
define('BOX_EXTRACT_PATTERN_DEFAULT', '__HALT'.'_COMPILER(); ?>');

/*
 * The open-ended stub pattern.
 *
 * @var string
 */
define('BOX_EXTRACT_PATTERN_OPEN', '__HALT'."_COMPILER(); ?>\r\n");

/**
 * Extracts a phar without the extension.
 *
 * This class is a rewrite of the `Extract_Phar` class that is included
 * in the default stub for all phars. The class is designed to work from
 * inside and outside of a phar. Unlike the original class, the stub
 * length must be specified.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 *
 * @see https://github.com/php/php-src/blob/master/ext/phar/shortarc.php
 */
class Extract
{
    /**
     * The default stub pattern.
     *
     * @var string
     */
    const PATTERN_DEFAULT = BOX_EXTRACT_PATTERN_DEFAULT;

    /**
     * The open-ended stub pattern.
     *
     * @var string
     */
    const PATTERN_OPEN = BOX_EXTRACT_PATTERN_OPEN;

    /**
     * The gzip compression flag.
     *
     * @var int
     */
    const GZ = 0x1000;

    /**
     * The bzip2 compression flag.
     *
     * @var int
     */
    const BZ2 = 0x2000;

    /**
     * @var int
     */
    const MASK = 0x3000;

    /**
     * The phar file path to extract.
     *
     * @var string
     */
    private $file;

    /**
     * The open file handle.
     *
     * @var resource
     */
    private $handle;

    /**
     * The length of the stub in the phar.
     *
     * @var int
     */
    private $stub;

    /**
     * Sets the file to extract and the stub length.
     *
     * @param string $file the file path
     * @param int    $stub the stub length
     *
     * @throws InvalidArgumentException if the file does not exist
     */
    public function __construct($file, $stub)
    {
        if (!is_file($file)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The path "%s" is not a file or does not exist.',
                    $file
                )
            );
        }

        $this->file = $file;
        $this->stub = $stub;
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
     * @param string $file    the phar file path
     * @param string $pattern the stub end pattern
     *
     * @throws InvalidArgumentException if the pattern could not be found
     * @throws RuntimeException         if the phar could not be read
     *
     * @return int the stub length
     */
    public static function findStubLength(
        $file,
        $pattern = self::PATTERN_OPEN
    ) {
        if (!($fp = fopen($file, 'rb'))) {
            throw new RuntimeException(
                sprintf(
                    'The phar "%s" could not be opened for reading.',
                    $file
                )
            );
        }

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
     * Extracts the phar to the directory path.
     *
     * If no directory path is given, a temporary one will be generated and
     * returned. If a directory path is given, the returned directory path
     * will be the same.
     *
     * @param string $dir the directory to extract to
     *
     * @throws LengthException
     * @throws RuntimeException
     *
     * @return string the directory extracted to
     */
    public function go($dir = null)
    {
        // set up the output directory
        if (null === $dir) {
            $dir = rtrim(sys_get_temp_dir(), '\\/')
                .DIRECTORY_SEPARATOR
                .'pharextract'
                .DIRECTORY_SEPARATOR
                .basename($this->file, '.phar');
        } else {
            $dir = realpath($dir);
        }

        // skip if already extracted
        $md5 = $dir.DIRECTORY_SEPARATOR.md5_file($this->file);

        if (file_exists($md5)) {
            return $dir;
        }

        if (!is_dir($dir)) {
            $this->createDir($dir);
        }

        // open the file and skip stub
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

        // read the manifest
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
     * @param string $path the path to delete
     *
     * @throws RuntimeException if the path could not be deleted
     */
    public static function purge($path): void
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
     * @param string $path      the directory path
     * @param int    $chmod     the file mode
     * @param bool   $recursive Recursively create path?
     *
     * @throws RuntimeException if the path could not be created
     */
    private function createDir($path, $chmod = 0777, $recursive = true): void
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
     * @param string $path     the file path
     * @param string $contents the file contents
     * @param int    $mode     the file mode
     *
     * @throws RuntimeException if the file could not be created
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
     * Extracts a single file from the phar.
     *
     * @param array $info the file information
     *
     * @throws RuntimeException         if the file could not be extracted
     * @throws UnexpectedValueException if the crc32 checksum does not
     *                                  match the expected value
     *
     * @return string the file data
     */
    private function extractFile($info)
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
     *
     * @throws RuntimeException if the file could not be opened
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
     * @param int $bytes the number of bytes
     *
     * @throws RuntimeException if the read fails
     *
     * @return string the binary string read
     */
    private function read($bytes)
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
    private function readManifest()
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
