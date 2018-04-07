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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use const E_USER_DEPRECATED;
use function trigger_error;

/**
 * @deprecated
 */
final class Build extends Compile
{
    /**
     * {@inheritdoc}
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        @trigger_error(
            $deprecationMessage = 'The command "build" is deprecated. Use "compile" instead.',
            E_USER_DEPRECATED
        );

        $io->warning($deprecationMessage);

        return parent::run($input, $output);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setName('build');
        $this->setDescription('Builds a new PHAR');
    }
}
