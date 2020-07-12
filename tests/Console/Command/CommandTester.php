<?php

declare(strict_types=1);

namespace KevinGH\Box\Console\Command;

use Symfony\Component\Console\Tester\CommandTester as SymfonyCommandTester;

class CommandTester extends SymfonyCommandTester
{
    /**
     * {@inheritdoc}
     */
    public function execute(array $input, array $options = []): int
    {
        if ('compile' === $input['command']) {
            $input['--no-parallel'] = null;
        }

        return parent::execute($input, $options);
    }
}
