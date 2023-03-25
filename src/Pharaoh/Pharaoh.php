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
 * This file originates from https://github.com/paragonie/pharaoh.
 *
 * For maintenance reasons it had to be in-lined within Box. To simplify the
 * configuration for PHP-CS-Fixer, the original license is in-lined as follows:
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2015 - 2018 Paragon Initiative Enterprises
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace KevinGH\Box\Pharaoh;

use JetBrains\PhpStorm\ArrayShape;
use KevinGH\Box\Phar\PharPhpSettings;
use KevinGH\Box\PharInfo\PharInfo;
use ParagonIE\ConstantTime\Hex;
use Phar;
use Webmozart\Assert\Assert;
use function KevinGH\Box\FileSystem\copy;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\mkdir;
use function KevinGH\Box\FileSystem\remove;
use function KevinGH\Box\FileSystem\tempnam;
use function random_bytes;
use function sys_get_temp_dir;
use const DIRECTORY_SEPARATOR;

/**
 * Pharaoh is a wrapper around Phar. This is necessary because the Phar API is quite limited and will crash if say two
 * PHARs with the same alias are loaded.
 */
final class Pharaoh
{
    private static string $stubfile;

    private Phar $phar;
    private string $tmp;
    private string $file;
    private string $fileName;
    private ?PharInfo $pharInfo = null;
    private ?string $path = null;

    #[ArrayShape(['hash' => 'string', 'hash_type' => 'string'])]
    private array|false $signature;

    public function __construct(string $file)
    {
        Assert::readable($file);
        Assert::false(
            PharPhpSettings::isReadonly(),
            'Pharaoh cannot be used if phar.readonly is enabled in php.ini',
        );

        self::initStubFileName();

        $tmp = self::createTmpDir();
        $this->initPhar($file);

        self::extractPhar($this->phar, $tmp);

        $this->tmp = $tmp;
        $this->file = $file;
        $this->fileName = basename($file);
    }

    public function __destruct()
    {
        unset($this->pharInfo);

        $path = $this->phar->getPath();
        unset($this->phar);

        Phar::unlinkArchive($path);

        remove($this->tmp);
    }

    public function getPhar(): Phar
    {
        return $this->phar;
    }

    public function getTmp(): string
    {
        return $this->tmp;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getPharInfo(): PharInfo
    {
        if (null === $this->pharInfo || $this->path !== $this->phar->getPath()) {
            $this->path = $this->phar->getPath();
            $this->pharInfo = new PharInfo($this->path);
        }

        return $this->pharInfo;
    }

    public function getSignature(): array|false
    {
        return $this->signature;
    }

    private function initPhar(string $file): void
    {
        $extension = self::getExtension($file);

        // We have to give every one a different alias, or it pukes.
        $alias = Hex::encode(random_bytes(16)).$extension;

        if (!str_ends_with($alias, '.phar')) {
            $alias .= '.phar';
        }

        $tmpFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.$alias;
        copy($file, $tmpFile);

        $phar = new Phar($tmpFile);
        $this->signature = $phar->getSignature();

        $phar->setAlias($alias);
        $this->phar = $phar;
    }

    private static function initStubFileName(): void
    {
        if (!isset(self::$stubfile)) {
            self::$stubfile = Hex::encode(random_bytes(12)).'.pharstub';
        }
    }

    private static function createTmpDir(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'box_');

        remove($tmp);
        mkdir($tmp, 0o755);

        return $tmp;
    }

    private static function extractPhar(Phar $phar, string $tmp): void
    {
        // Extract the PHAR content
        $phar->extractTo($tmp);

        // Extract the stub; Phar::extractTo() does not do it since it
        // is internal to the PHAR.
        dump_file(
            $tmp.DIRECTORY_SEPARATOR.self::$stubfile,
            $phar->getStub(),
        );
    }

    private static function getExtension(string $file): string
    {
        $lastExtension = pathinfo($file, PATHINFO_EXTENSION);
        $extension = '';

        while ('' !== $lastExtension) {
            $extension = '.'.$lastExtension.$extension;
            $file = mb_substr($file, 0, -(mb_strlen($lastExtension) + 1));
            $lastExtension = pathinfo($file, PATHINFO_EXTENSION);
        }

        return $extension;
    }
}
