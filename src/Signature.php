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
use KevinGH\Box\Verifier\Hash;
use KevinGH\Box\Verifier\PublicKeyDelegate;
use PharException;
use const SEEK_END;
use const SEEK_SET;
use function fclose;
use function filesize;
use function fopen;
use function fread;
use function fseek;
use function ini_get;
use function is_resource;
use function realpath;
use function sprintf;
use function strlen;
use function strtoupper;
use function unpack;

/**
 * Retrieves and verifies a PHAR's signature without using the extension.
 *
 * While the PHAR extension is not used to retrieve or verify a PHAR's signature, other extensions may still be needed
 * to properly process the signature.
 *
 * @private
 */
final class Signature
{
    /**
     * The recognized PHAR signatures types.
     */
    private const TYPES = [
        [
            'name' => 'MD5',
            'flag' => 0x01,
            'size' => 16,
            'class' => Hash::class,
        ],
        [
            'name' => 'SHA-1',
            'flag' => 0x02,
            'size' => 20,
            'class' => Hash::class,
        ],
        [
            'name' => 'SHA-256',
            'flag' => 0x03,
            'size' => 32,
            'class' => Hash::class,
        ],
        [
            'name' => 'SHA-512',
            'flag' => 0x04,
            'size' => 64,
            'class' => Hash::class,
        ],
        [
            'name' => 'OpenSSL',
            'flag' => 0x10,
            'size' => null,
            'class' => PublicKeyDelegate::class,
        ],
    ];

    /** @var string The PHAR file path */
    private $file;

    /** @var resource The file handle */
    private $handle;

    /** @var int The size of the file */
    private $size;

    public function __construct(string $path)
    {
        Assertion::file($path);

        $this->file = realpath($path);

        $this->size = filesize($path);
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Returns the signature for the PHAR.
     *
     * The value returned is identical to that of `Phar->getSignature()`. If
     * $required is not given, it will default to the `phar.require_hash`
     * current value.
     *
     * @param bool $required Is the signature required?
     *
     * @throws PharException If the phar is not valid
     *
     * @return null|array The signature
     */
    public function get(?bool $required = null): ?array
    {
        if (null === $required) {
            $required = (bool) ini_get('phar.require_hash');
        }

        $this->seek(-4, SEEK_END);

        if ('GBMB' !== $this->read(4)) {
            if ($required) {
                throw new PharException(
                    sprintf(
                        'The phar "%s" is not signed.',
                        $this->file
                    )
                );
            }

            return null;
        }

        $this->seek(-8, SEEK_END);

        $flag = unpack('V', $this->read(4));
        $flag = $flag[1];

        foreach (self::TYPES as $type) {
            if ($flag === $type['flag']) {
                break;
            }

            unset($type);
        }

        if (!isset($type)) {
            throw new PharException(
                sprintf(
                    'The signature type (%x) is not recognized for the phar "%s".',
                    $flag,
                    $this->file
                )
            );
        }

        $offset = -8;

        if (0x10 === $type['flag']) {
            $offset = -12;

            $this->seek(-12, SEEK_END);

            $type['size'] = unpack('V', $this->read(4));
            $type['size'] = $type['size'][1];
        }

        $this->seek($offset - $type['size'], SEEK_END);

        $hash = $this->read($type['size']);
        $hash = unpack('H*', $hash);

        return [
            'hash_type' => $type['name'],
            'hash' => strtoupper($hash[1]),
        ];
    }

    public function verify(): bool
    {
        $signature = $this->get();

        $size = $this->size;
        $type = null;

        foreach (self::TYPES as $type) {
            if ($type['name'] === $signature['hash_type']) {
                if (0x10 === $type['flag']) {
                    $this->seek(-12, SEEK_END);

                    $less = $this->read(4);
                    $less = unpack('V', $less);
                    $less = $less[1];

                    $size -= 12 + $less;
                } else {
                    $size -= 8 + $type['size'];
                }

                break;
            }
        }

        $this->seek(0);

        /** @var Verifier $verify */
        $verify = new $type['class']($type['name'], $this->file);

        $buffer = 64;

        while (0 < $size) {
            if ($size < $buffer) {
                $buffer = $size;
                $size = 0;
            }

            $verify->update($this->read($buffer));

            $size -= $buffer;
        }

        return $verify->verify($signature['hash']);
    }

    /**
     * Closes the open file handle.
     */
    private function close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);

            $this->handle = null;
        }
    }

    /**
     * Returns the file handle. If the file handle is not opened, it will be automatically opened.
     *
     * @return resource the file handle
     */
    private function handle()
    {
        if (!$this->handle) {
            $this->handle = fopen($this->file, 'rb');
        }

        return $this->handle;
    }

    /**
     * Reads a number of bytes from the file.
     *
     * @param int $bytes the number of bytes
     *
     * @return string the read bytes
     */
    private function read(int $bytes): string
    {
        $read = fread($this->handle(), $bytes);

        Assertion::same(strlen($read), $bytes);

        return $read;
    }

    /**
     * Seeks to a specific point in the file.
     *
     * @param int $offset the offset to seek
     * @param int $whence the direction
     */
    private function seek(int $offset, int $whence = SEEK_SET): void
    {
        fseek($this->handle(), $offset, $whence);
    }
}
