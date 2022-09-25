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

use function KevinGH\Box\register_aliases;
use Laravel\SerializableClosure\Support\ClosureStream;
use org\bovigo\vfs\vfsStreamWrapper;

register_aliases();

vfsStreamWrapper::register();
ClosureStream::register();
