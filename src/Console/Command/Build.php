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

use KevinGH\Box\Console\IO\IO;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @deprecated
 *
 * @private
 *
 * TODO: make Compile final when Build is removed
 */
final class Build extends Compile
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setName('build');
        $this->setDescription('Builds a new PHAR (deprecated, use "compile" instead)');
    }

    /**
     * {@inheritdoc}
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        $io = new IO($input, $output);

        $io->warning('The command "build" is deprecated. Use "compile" instead.');

        return parent::run($input, $output);
    }
}
