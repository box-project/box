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

use Error;
use Exception;
use ParagonIE\ConstantTime\Hex;
use Phar;
use function copy;
use function file_put_contents;
use function ini_get;
use function is_dir;
use function is_readable;
use function is_string;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use const DIRECTORY_SEPARATOR;

/**
 * Class Pharaoh.
 */
class Pharaoh
{
    /**
     * @var Phar
     */
    public $phar;

    /**
     * @var string
     */
    public $tmp;

    /**
     * @var string
     */
    public static $stubfile;

    /**
     * Pharaoh constructor.
     * @param  string    $alias
     * @throws PharError
     * @throws Error
     * @throws Exception
     */
    public function __construct(string $file, $alias = null)
    {
        if (!is_readable($file)) {
            throw new PharError($file.' cannot be read');
        }
        if ('1' == ini_get('phar.readonly')) {
            throw new PharError("Pharaoh cannot be used if phar.readonly is enabled in php.ini\n");
        }

        // Set the static variable here
        if (empty(self::$stubfile)) {
            self::$stubfile = Hex::encode(random_bytes(12)).'.pharstub';
        }

        if (empty($alias)) {
            // We have to give every one a different alias, or it pukes.
            $alias = Hex::encode(random_bytes(16)).'.phar';
        }

        if (!copy($file, $tmpFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.$alias)) {
            throw new Error('Could not create temporary file');
        }

        $this->phar = new Phar($tmpFile);
        $this->phar->setAlias($alias);

        // Make a random folder in /tmp
        /** @var string|bool $tmp */
        $tmp = tempnam(sys_get_temp_dir(), 'phr_');
        if (!is_string($tmp)) {
            throw new Error('Could not create temporary file');
        }

        $this->tmp = $tmp;
        unlink($this->tmp);
        if (!mkdir($this->tmp, 0o755, true) && !is_dir($this->tmp)) {
            throw new Error('Could not create temporary directory');
        }

        // Let's extract to our temporary directory
        $this->phar->extractTo($this->tmp);

        // Also extract the stub
        file_put_contents(
            $this->tmp.'/'.self::$stubfile,
            $this->phar->getStub()
        );
    }

    public function __destruct()
    {
        $path = $this->phar->getPath();
        unset($this->phar);

        Phar::unlinkArchive($path);
    }
}
