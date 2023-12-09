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

namespace KevinGH\Box\Composer\Throwable;

use JetBrains\PhpStorm\Pure;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;
use function implode;
use function sprintf;
use const PHP_EOL;

final class UndetectableComposerVersion extends RuntimeException
{
    #[Pure]
    public function __construct(
        string $message,
        public readonly ?Process $process = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function forFailedProcess(Process $process): self
    {
        $previous = new ProcessFailedException($process);

        return new self(
            sprintf(
                'Could not detect the Composer version: %s',
                $previous->getMessage(),
            ),
            $process,
            previous: $previous,
        );
    }

    public static function forOutput(Process $process, string $normalizedOutput): self
    {
        return new self(
            implode(
                PHP_EOL,
                [
                    'Could not determine the Composer version from the following output:',
                    $normalizedOutput,
                ],
            ),
            $process,
        );
    }
}
