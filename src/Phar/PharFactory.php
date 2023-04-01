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

namespace KevinGH\Box\Phar;

use KevinGH\Box\Pharaoh\InvalidPhar;
use Phar;
use PharData;
use Symfony\Component\Filesystem\Path;
use Throwable;
use function file_exists;

/**
 * Factory class to instantiate an _existing_ file (i.e. not to create a brand-new PHAR object).
 * It is a thin wrapper around the native PHP constructor but with more friendly errors upon failure.
 */
final class PharFactory
{
    private function __construct()
    {
    }

    /**
     * @throws InvalidPhar
     */
    public static function create(string $file): Phar|PharData
    {
        if (!Path::isLocal($file)) {
            // This is needed as otherwise Phar::__construct() does correctly bail out on a URL
            // path, but not on other non-local variants, e.g. FTPS, which case it may fail still
            // but after a timeout, which is too slow.
            throw InvalidPhar::fileNotLocal($file);
        }

        if (!file_exists($file)) {
            // We need to check this case since the goal of this factory is to instantiate an existing
            // PHAR, not create a new one.
            throw InvalidPhar::fileNotFound($file);
        }

        try {
            return new Phar($file);
        } catch (Throwable $cannotCreatePhar) {
            // Continue
        }

        try {
            return new PharData($file);
        } catch (Throwable) {
            throw InvalidPhar::forPharAndPharData($file, $cannotCreatePhar);
        }
    }

    /**
     * @throws InvalidPhar
     */
    public static function createPhar(string $file): Phar
    {
        if (!Path::isLocal($file)) {
            // This is needed as otherwise Phar::__construct() does correctly bail out on a URL
            // path, but not on other non-local variants, e.g. FTPS, which case it may fail still
            // but after a timeout, which is too slow.
            throw InvalidPhar::fileNotLocal($file);
        }

        if (!file_exists($file)) {
            // We need to check this case since the goal of this factory is to instantiate an existing
            // PHAR, not create a new one.
            throw InvalidPhar::fileNotFound($file);
        }

        try {
            return new Phar($file);
        } catch (Throwable $throwable) {
            throw InvalidPhar::forPhar($file, $throwable);
        }
    }

    /**
     * @throws InvalidPhar
     */
    public static function createPharData(string $file): PharData
    {
        if (!Path::isLocal($file)) {
            // This is needed as otherwise Phar::__construct() does correctly bail out on a URL
            // path, but not on other non-local variants, e.g. FTPS, which case it may fail still
            // but after a timeout, which is too slow.
            throw InvalidPhar::fileNotLocal($file);
        }

        if (!file_exists($file)) {
            // We need to check this case since the goal of this factory is to instantiate an existing
            // PHAR, not create a new one.
            throw InvalidPhar::fileNotFound($file);
        }

        try {
            return new PharData($file);
        } catch (Throwable $throwable) {
            throw InvalidPhar::forPharData($file, $throwable);
        }
    }
}
