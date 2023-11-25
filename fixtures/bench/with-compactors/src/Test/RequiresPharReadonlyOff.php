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

namespace BenchTest\Test;

use BenchTest\Phar\PharPhpSettings;

/**
 * @private
 */
trait RequiresPharReadonlyOff
{
    private function markAsSkippedIfPharReadonlyIsOn(): void
    {
        if (PharPhpSettings::isReadonly()) {
            $this->markTestSkipped(
                'Requires phar.readonly to be set to 0. Either update your php.ini file or run this test with '
                .'php -d phar.readonly=0.',
            );
        }
    }
}
