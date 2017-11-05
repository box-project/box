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

namespace KevinGH\Box\Tests\Command;

use KevinGH\Box\Command\Extract;
use KevinGH\Box\Test\CommandTestCase;
use Phar;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @coversNothing
 */
class ExtractTest extends CommandTestCase
{
    public function testExecute(): void
    {
        $rand = 'test-'.random_int(0, getrandmax()).'.phar';
        $phar = new Phar($rand);
        $phar->addFromString('a/b/c/d.php', '<?php echo "Hello!";');
        $phar->addFromString('a/b/c/e.php', '<?php echo "Goodbye!";');

        unset($phar);

        $tester = $this->getTester();
        $tester->execute(
            [
                'command' => 'extract',
                'phar' => $rand,
                '--pick' => ['a/b/c/e.php'],
                '--out' => 'extracted',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'
Extracting files from the Phar...
Done.

OUTPUT;

        $this->assertSame(
            '<?php echo "Goodbye!";',
            file_get_contents('extracted/a/b/c/e.php')
        );
        $this->assertSame($expected, $this->getOutput($tester));
    }

    public function testExecuteAlternate(): void
    {
        $rand = 'test-'.random_int(0, getrandmax()).'.phar';
        $phar = new Phar($rand);
        $phar->addFromString('a/b/c/d.php', '<?php echo "Hello!";');
        $phar->addFromString('a/b/c/e.php', '<?php echo "Goodbye!";');

        unset($phar);

        $tester = $this->getTester();
        $tester->execute(
            [
                'command' => 'extract',
                'phar' => $rand,
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'
Extracting files from the Phar...
Done.

OUTPUT;

        $this->assertSame(
            '<?php echo "Hello!";',
            file_get_contents("$rand-contents/a/b/c/d.php")
        );
        $this->assertSame(
            '<?php echo "Goodbye!";',
            file_get_contents("$rand-contents/a/b/c/e.php")
        );
        $this->assertSame($expected, $this->getOutput($tester));
    }

    public function testExecuteNotExist(): void
    {
        $tester = $this->getTester();
        $tester->execute(
            [
                'command' => 'extract',
                'phar' => 'test.phar',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'
Extracting files from the Phar...
The path "test.phar" is not a file or does not exist.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));
    }

    protected function getCommand()
    {
        return new Extract();
    }
}
