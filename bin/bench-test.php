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

use Fidry\FileSystem\FS;
use KevinGH\Box\Benchmark\BenchResultReader;
use Webmozart\Assert\Assert;
use function number_format;
use const PHP_EOL;

require __DIR__.'/../vendor/autoload.php';

$benchmarkResultPath = __DIR__.'/../dist/bench-result.xml';

Assert::fileExists($benchmarkResultPath);

$xml = FS::getFileContents($benchmarkResultPath);
$meanTimes = BenchResultReader::readMeanTimes($xml);

$parallelTime = $meanTimes['with compactors; parallel processing'];
$noParallelTime = $meanTimes['with compactors; no parallel processing'];

$formatMeanTime = static fn (float $mean) => number_format($mean).'µs';

$maxParallelTimeTarget = .9 * $noParallelTime;

echo 'Benchmark results check:'.PHP_EOL;
echo '========================'.PHP_EOL;
echo PHP_EOL;
echo 'With parallelization: '.$formatMeanTime($parallelTime).PHP_EOL;
echo 'Without parallelization: '.$formatMeanTime($noParallelTime).PHP_EOL;
echo 'Max parallelization target: '.$formatMeanTime($maxParallelTimeTarget).PHP_EOL;

if ($parallelTime <= $maxParallelTimeTarget) {
    echo 'OK.'.PHP_EOL;

    exit(0);
}

$relativeDifference = number_format(
    $parallelTime * 100. / $maxParallelTimeTarget - 100.,
    2,
);

if ($relativeDifference >= 0) {
    $relativeDifference = '+'.$relativeDifference;
}

echo 'Failed!'.PHP_EOL;
echo 'Missed the target by '.$relativeDifference.'%'.PHP_EOL;

exit(1);
