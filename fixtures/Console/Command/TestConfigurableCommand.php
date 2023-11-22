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

use Fidry\Console\Command\Command;
use Fidry\Console\Command\Configuration;
use Fidry\Console\ExitCode;
use Fidry\Console\IO;
use KevinGH\Box\Configuration\Configuration as BoxConfiguration;

class TestConfigurableCommand implements Command
{
    public BoxConfiguration $config;

    public bool $allowNoFile = false;

    public function getConfiguration(): Configuration
    {
        return new Configuration(
            'TestCommand',
            'Command used to test ConfigOption',
            '',
            [],
            [
                ConfigOption::getOptionInput(),
            ],
        );
    }

    public function execute(IO $io): int
    {
        $this->config = ConfigOption::getConfig($io, $this->allowNoFile);

        return ExitCode::SUCCESS;
    }
}
