<?php

declare(strict_types=1);

namespace KevinGH\Box\Phar;


use KevinGH\Box\Pharaoh\InvalidPhar;
use Phar;
use PharData;
use Throwable;


final class PharFactory
{
    private function __construct()
    {
    }

    /**
     * @throws InvalidPhar
     */
    public static function createPhar(string $file): Phar
    {
        try {
            return new Phar($file);
        } catch (Throwable $throwable) {
            throw InvalidPhar::create($file, $throwable);
        }
    }

    /**
     * @throws InvalidPhar
     */
    public static function createPharData(string $file): PharData
    {
        try {
            return new PharData($file);
        } catch (Throwable $throwable) {
            throw InvalidPhar::create($file, $throwable);
        }
    }
}
