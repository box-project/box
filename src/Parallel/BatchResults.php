<?php

declare(strict_types=1);

namespace KevinGH\Box\Parallel;

use KevinGH\Box\NotInstantiable;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use function array_map;
use function iter\toArray;
use function KevinGH\Box\unique_id;

final class BatchResults
{
    use NotInstantiable;

    public static function createFilename(): string
    {
        return unique_id('batch-').'.json';
    }

    public static function createFileContent(): string
    {
        return unique_id('batch-').'.json';
    }

    /**
     * @return iterable<SplFileInfo>
     */
    public static function collect(string $source): iterable
    {
        return Finder::create()
            ->files()
            ->in($source)
            ->name('batch-*.json');
    }
}