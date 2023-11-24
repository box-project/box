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

namespace KevinGH\Box\PhpScoper;

use Humbug\PhpScoper\Configuration\Configuration as PhpScoperConfiguration;
use Humbug\PhpScoper\Container;
use InvalidArgumentException;
use KevinGH\Box\NotInstantiable;
use Throwable;
use function sprintf;

final class ConfigurationFactory
{
    use NotInstantiable;

    public static function create(?string $filePath): PhpScoperConfiguration
    {
        $configFactory = (new Container())->getConfigurationFactory();

        try {
            return $configFactory->create($filePath);
        } catch (Throwable $throwable) {
            throw new InvalidArgumentException(
                sprintf(
                    'Could not create a PHP-Scoper config from the file "%s": %s',
                    $filePath,
                    $throwable->getMessage(),
                ),
                $throwable->getCode(),
                $throwable,
            );
        }
    }
}
