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
use KevinGH\Box\Exception\Exception;
use KevinGH\Box\Exception\FileExceptionFactory;
use KevinGH\Box\Exception\OpenSslExceptionFactory;
use KevinGH\Box\Signature\Hash;
use KevinGH\Box\Signature\PublicKeyDelegate;
use KevinGH\Box\Signature\VerifyInterface;
use PharException;

/**
 * Retrieves and verifies a phar's signature without using the extension.
 *
 * While the phar extension is not used to retrieve or verify a phar's
 * signature, other extensions may still be needed to properly process
 * the signature.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Signature
{
    /**
     * The phar file path.
     *
     * @var string
     */
    private $file;

    /**
     * The file handle.
     *
     * @var resource
     */
    private $handle;

    /**
     * The size of the file.
     *
     * @var int
     */
    private $size;

    /**
     * The recognized signature types.
     *
     * @var array
     */
    private static $types = [
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

    /**
     * Sets the phar file path.
     *
     * @param string $path the phar file path
     *
     * @throws Exception
     * @throws FileExceptionFactory if the file does not exist
     */
    public function __construct($path)
    {
        Assertion::file($path);

        $this->file = realpath($path);

        if (false === ($this->size = @filesize($path))) {
            throw FileExceptionFactory::createForLastError();
        }
    }

    /**
     * Closes the open file handle.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Creates a new instance of Signature.
     *
     * @param string $path the phar file path
     *
     * @return Signature the new instance
     */
    public static function create($path)
    {
        return new self($path);
    }

    /**
     * Returns the signature for the phar.
     *
     * The value returned is identical to that of `Phar->getSignature()`. If
     * $required is not given, it will default to the `phar.require_hash`
     * current value.
     *
     * @param bool $required Is the signature required?
     *
     * @throws PharException if the phar is not valid
     *
     * @return array the signature
     */
    public function get($required = null)
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

        foreach (self::$types as $type) {
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

    /**
     * Verifies the signature of the phar.
     *
     * @throws Exception
     * @throws FileExceptionFactory    if the private key could not be read
     * @throws OpenSslExceptionFactory if there is an OpenSSL error
     *
     * @return bool TRUE if verified, FALSE if not
     */
    public function verify()
    {
        $signature = $this->get();

        $size = $this->size;
        $type = null;

        foreach (self::$types as $type) {
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

        /** @var $verify VerifyInterface */
        $verify = new $type['class']();
        $verify->init($type['name'], $this->file);

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
            @fclose($this->handle);

            $this->handle = null;
        }
    }

    /**
     * Returns the file handle.
     *
     * If the file handle is not opened, it will be automatically opened.
     *
     * @throws Exception
     * @throws FileExceptionFactory if the file could not be opened
     *
     * @return resource the file handle
     */
    private function handle()
    {
        if (!$this->handle) {
            if (!($this->handle = @fopen($this->file, 'rb'))) {
                throw FileExceptionFactory::lastError();
            }
        }

        return $this->handle;
    }

    /**
     * Reads a number of bytes from the file.
     *
     * @param int $bytes the number of bytes
     *
     * @throws Exception
     * @throws FileExceptionFactory if the file could not be read
     *
     * @return string the read bytes
     */
    private function read($bytes)
    {
        if (false === ($read = @fread($this->handle(), $bytes))) {
            throw FileExceptionFactory::lastError();
        }

        if (($actual = strlen($read)) !== $bytes) {
            throw FileExceptionFactory::create(
                'Only read %d of %d bytes from "%s".',
                $actual,
                $bytes,
                $this->file
            );
        }

        return $read;
    }

    /**
     * Seeks to a specific point in the file.
     *
     * @param int $offset the offset to seek
     * @param int $whence the direction
     *
     * @throws Exception
     * @throws FileExceptionFactory if the file could not be seeked
     */
    private function seek($offset, $whence = SEEK_SET): void
    {
        if (-1 === @fseek($this->handle(), $offset, $whence)) {
            throw FileExceptionFactory::lastError();
        }
    }
}
