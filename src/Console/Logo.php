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

namespace KevinGH\Box\Console;

use KevinGH\Box\NotInstantiable;

/**
 * @private
 */
final class Logo
{
    use NotInstantiable;

    public const LOGO_ASCII = <<<'ASCII'

            ____
           / __ )____  _  __
          / __  / __ \| |/_/
         / /_/ / /_/ />  <
        /_____/\____/_/|_|



        ASCII;
}
