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

namespace KevinGH\Box\Console\Command;

use KevinGH\Box\Console\Application;
use KevinGH\Box\Console\ConfigurationHelper;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

/**
 * Tiny Symfony Command adapter to allow the command to easily access to the typehinted helpers which are configured
 * in the application.
 *
 * @see Application
 */
abstract class Command extends SymfonyCommand
{
    final protected function getConfigurationHelper(): ConfigurationHelper
    {
        return $this->getHelper('config');
    }
}
