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

namespace KevinGH\Box\Tests\Command\Key;

use KevinGH\Box\Command\Key\Create;
use KevinGH\Box\Test\CommandTestCase;
use KevinGH\Box\Test\FixedResponse;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @coversNothing
 */
class CreateTest extends CommandTestCase
{
    public function testExecute(): void
    {
        $this->app->getHelperSet()->set(new FixedResponse('test'));

        $tester = $this->getTester();
        $tester->execute(
            [
                'command' => 'key:create',
                '--bits' => 512,
                '--out' => 'test.key',
                '--public' => 'test.pub',
                '--prompt' => true,
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'
Generating 512 bit private key...
Writing private key...
Writing public key...

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));
        $this->assertRegExp('/PRIVATE KEY/', file_get_contents('test.key'));
        $this->assertRegExp('/PUBLIC KEY/', file_get_contents('test.pub'));
    }

    protected function getCommand()
    {
        return new Create();
    }
}
