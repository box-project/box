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

use KevinGH\Box\Phar\IncompariblePhars;
use KevinGH\Box\Phar\PharInfo;
use ParagonIE\ConstantTime\Hex;
use ParagonIE_Sodium_File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use function array_keys;
use function array_merge;
use function array_values;
use function count;
use function hash_file;
use function max;
use function mb_strlen;
use function mb_strtolower;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function str_repeat;

class PharDiff
{
    /**
     * @var array<string, string>
     */
    protected array $c = [
        '' => "\033[0;39m",
        'red' => "\033[0;31m",
        'green' => "\033[0;32m",
        'blue' => "\033[1;34m",
        'cyan' => "\033[1;36m",
        'silver' => "\033[0;37m",
        'yellow' => "\033[0;93m",
    ];

    /** @var array<int, PharInfo> */
    private array $pharInfos = [];

    private bool $verbose = false;

    public function __construct(PharInfo $pharInfoA, PharInfo $pharInfoB)
    {
        if ($pharInfoA->hasPubKey() || $pharInfoB->hasPubKey()) {
            throw IncompariblePhars::signedPhars();
        }

        $this->pharInfos = [$pharInfoA, $pharInfoB];
    }

    /**
     * Get hashes of all the files in the two arrays.
     *
     * @return array<int, array<mixed, string>>
     */
    public function hashChildren(string $algo, string $dirA, string $dirB): array
    {
        /**
         * @var string $a
         * @var string $b
         */
        $a = $b = '';
        $filesA = $this->listAllFiles($dirA);
        $filesB = $this->listAllFiles($dirB);
        $numFiles = max(count($filesA), count($filesB));

        // Array of two empty arrays
        $hashes = [[], []];
        for ($i = 0; $i < $numFiles; ++$i) {
            $thisFileA = (string) $filesA[$i];
            $thisFileB = (string) $filesB[$i];
            if (isset($filesA[$i])) {
                $a = preg_replace('#^'.preg_quote($dirA, '#').'#', '', $thisFileA);
                if (isset($filesB[$i])) {
                    $b = preg_replace('#^'.preg_quote($dirB, '#').'#', '', $thisFileB);
                } else {
                    $b = $a;
                }
            } elseif (isset($filesB[$i])) {
                $b = preg_replace('#^'.preg_quote($dirB, '#').'#', '', $thisFileB);
                $a = $b;
            }

            if (isset($filesA[$i])) {
                // A exists
                if ('blake2b' === mb_strtolower($algo)) {
                    $hashes[0][$a] = Hex::encode(ParagonIE_Sodium_File::generichash($thisFileA));
                } else {
                    $hashes[0][$a] = hash_file($algo, $thisFileA);
                }
            } elseif (isset($filesB[$i])) {
                // A doesn't exist, B does
                $hashes[0][$a] = '';
            }

            if (isset($filesB[$i])) {
                // B exists
                if ('blake2b' === mb_strtolower($algo)) {
                    $hashes[1][$b] = Hex::encode(ParagonIE_Sodium_File::generichash($thisFileB));
                } else {
                    $hashes[1][$b] = hash_file($algo, $thisFileB);
                }
            } elseif (isset($filesA[$i])) {
                // B doesn't exist, A does
                $hashes[1][$b] = '';
            }
        }

        return $hashes;
    }

    /**
     * List all the files in a directory (and subdirectories).
     *
     * @param string $folder    - start searching here
     * @param string $extension - extensions to match
     */
    private function listAllFiles(string $folder, string $extension = '*'): array
    {
        /**
         * @var array<mixed, string>       $fileList
         * @var string                     $i
         * @var string                     $file
         * @var RecursiveDirectoryIterator $dir
         * @var RecursiveIteratorIterator  $ite
         */
        $dir = new RecursiveDirectoryIterator($folder);
        $ite = new RecursiveIteratorIterator($dir);
        if ('*' === $extension) {
            $pattern = '/.*/';
        } else {
            $pattern = '/.*\.'.$extension.'$/';
        }
        $files = new RegexIterator($ite, $pattern, RegexIterator::GET_MATCH);

        /** @var array<string, string> $fileList */
        $fileList = [];

        /**
         * @var string[] $fileSub
         */
        foreach ($files as $fileSub) {
            // TODO: address this array_merge
            $fileList = array_merge($fileList, $fileSub);
        }

        /**
         * @var string $i
         * @var string $file
         */
        foreach ($fileList as $i => $file) {
            if (preg_match('#/\.{1,2}$#', (string) $file)) {
                unset($fileList[$i]);
            }
        }

        return array_values($fileList);
    }

    /**
     * Prints out all the differences of checksums of the files contained
     * in both PHP archives.
     */
    public function listChecksums(string $algo = 'sha384'): int
    {
        [$pharA, $pharB] = $this->hashChildren(
            $algo,
            $this->pharInfos[0]->getTmp(),
            $this->pharInfos[1]->getTmp(),
        );

        $diffs = 0;
        /** @var string $i */
        foreach (array_keys($pharA) as $i) {
            if (isset($pharA[$i], $pharB[$i])) {
                // We are NOT concerned about local timing attacks.
                if ($pharA[$i] !== $pharB[$i]) {
                    ++$diffs;
                    echo "\t", (string) $i,
                    "\n\t\t", $this->c['red'], $pharA[$i], $this->c[''],
                    "\t", $this->c['green'], $pharB[$i], $this->c[''],
                    "\n";
                } elseif (!empty($pharA[$i]) && empty($pharB[$i])) {
                    ++$diffs;
                    echo "\t", (string) $i,
                    "\n\t\t", $this->c['red'], $pharA[$i], $this->c[''],
                    "\t", str_repeat('-', mb_strlen($pharA[$i])),
                    "\n";
                } elseif (!empty($pharB[$i]) && empty($pharA[$i])) {
                    ++$diffs;
                    echo "\t", (string) $i,
                    "\n\t\t", str_repeat('-', mb_strlen($pharB[$i])),
                    "\t", $this->c['green'], $pharB[$i], $this->c[''],
                    "\n";
                }
            }
        }
        if (0 === $diffs) {
            if ($this->verbose) {
                echo 'No differences encountered.', PHP_EOL;
            }

            return 0;
        }

        return 1;
    }

    /**
     * Verbose mode says something when there are no differences.
     * By default, you can just check the return value.
     */
    public function setVerbose(bool $value): void
    {
        $this->verbose = $value;
    }
}
