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

namespace KevinGH\Box\Command;

use Herrera\Box\Box;
use Herrera\Box\Compactor\Php;
use Herrera\Box\StubGenerator;
use KevinGH\Box\Test\CommandTestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @coversNothing
 */
class AddTest extends CommandTestCase
{
    public function testExecute(): void
    {
        $this->preparePhar();

        file_put_contents(
            'goodbye.php',
            <<<CODE
<?php

/**
 * Just saying hello!
 */
echo "Goodbye, @name@!\n";
CODE
        );

        $tester = $this->getCommandTester();
        $tester->execute(
            [
                'command' => 'add',
                'phar' => 'test.phar',
                'file' => 'goodbye.php',
                'local' => 'src/hello.php',
                '--replace' => true,
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $dir = $this->dir.DIRECTORY_SEPARATOR;
        $expected = <<<OUTPUT
? Loading bootstrap file: {$dir}bootstrap.php
* Adding to the Phar...
? Setting replacement values...
  + @name@: world
? Registering compactors...
  + Herrera\\Box\\Compactor\\Php
? Adding file: {$dir}goodbye.php
* Done.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));

        $this->assertSame(
            'Goodbye, world!',
            trim(exec('php test.phar'))
        );
    }

    public function testExecuteBinary(): void
    {
        $this->preparePhar();

        file_put_contents(
            'goodbye.php',
            <<<CODE
<?php

/**
 * Just saying hello!
 */
echo "Goodbye, @name@!\n";
CODE
        );

        $tester = $this->getCommandTester();
        $tester->execute(
            [
                'command' => 'add',
                'phar' => 'test.phar',
                'file' => 'goodbye.php',
                'local' => 'src/hello.php',
                '--binary' => true,
                '--replace' => true,
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $dir = $this->dir.DIRECTORY_SEPARATOR;
        $expected = <<<OUTPUT
? Loading bootstrap file: {$dir}bootstrap.php
* Adding to the Phar...
? Setting replacement values...
  + @name@: world
? Registering compactors...
  + Herrera\\Box\\Compactor\\Php
? Adding binary file: {$dir}goodbye.php
* Done.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));

        $this->assertSame(
            'Goodbye, @name@!',
            trim(exec('php test.phar'))
        );
    }

    public function testExecuteStub(): void
    {
        $this->preparePhar();

        file_put_contents(
            'stub.php',
            '<?php echo "Hello, stub!\n"; __HALT_COMPILER();'
        );

        $tester = $this->getCommandTester();
        $tester->execute(
            [
                'command' => 'add',
                'phar' => 'test.phar',
                'file' => 'stub.php',
                '--stub' => true,
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $dir = $this->dir.DIRECTORY_SEPARATOR;
        $expected = <<<OUTPUT
? Loading bootstrap file: {$dir}bootstrap.php
* Adding to the Phar...
? Setting replacement values...
  + @name@: world
? Registering compactors...
  + Herrera\\Box\\Compactor\\Php
? Using stub file: {$dir}stub.php
* Done.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));
        $this->assertSame(
            'Hello, stub!',
            trim(exec('php test.phar'))
        );
    }

    public function testExecuteMain(): void
    {
        $this->preparePhar();

        file_put_contents(
            'main.php',
            <<<CODE
#!/usr/bin/env php
<?php

/**
 * Just saying sup!
 */
echo "Sup, @name@!\n";
CODE
        );

        $tester = $this->getCommandTester();
        $tester->execute(
            [
                'command' => 'add',
                'phar' => 'test.phar',
                'file' => 'main.php',
                'local' => 'bin/run',
                '--main' => true,
                '--replace' => true,
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $dir = $this->dir.DIRECTORY_SEPARATOR;
        $expected = <<<OUTPUT
? Loading bootstrap file: {$dir}bootstrap.php
* Adding to the Phar...
? Setting replacement values...
  + @name@: world
? Registering compactors...
  + Herrera\\Box\\Compactor\\Php
? Adding main file: {$dir}main.php
* Done.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));
        $this->assertSame(
            'Sup, world!',
            trim(exec('php test.phar'))
        );
    }

    public function testExecuteMissingLocal(): void
    {
        $tester = $this->getCommandTester();
        $exit = $tester->execute(
            [
                'command' => 'add',
                'phar' => 'test.phar',
                'file' => 'test.php',
            ]
        );

        $this->assertSame(1, $exit);
        $this->assertSame(
            "The local argument is required.\n",
            $this->getOutput($tester)
        );
    }

    public function testExecutePharNotExist(): void
    {
        file_put_contents('box.json', '{}');

        $tester = $this->getCommandTester();
        $exit = $tester->execute(
            [
                'command' => 'add',
                'phar' => 'test.phar',
                'file' => 'test.php',
                'local' => 'test.php',
            ]
        );

        $this->assertSame(1, $exit);
        $this->assertSame(
            "The path \"test.phar\" is not a file or does not exist.\n",
            $this->getOutput($tester)
        );
    }

    public function testExecuteFileNotExist(): void
    {
        file_put_contents('box.json', '{}');
        touch('test.phar');

        $tester = $this->getCommandTester();
        $exit = $tester->execute(
            [
                'command' => 'add',
                'phar' => 'test.phar',
                'file' => 'test.php',
                'local' => 'test.php',
            ]
        );

        $this->assertSame(1, $exit);
        $this->assertSame(
            "The path \"test.php\" is not a file or does not exist.\n",
            $this->getOutput($tester)
        );
    }

    public function testExecuteExists(): void
    {
        $this->preparePhar();

        touch('test.php');

        $tester = $this->getCommandTester();
        $exit = $tester->execute(
            [
                'command' => 'add',
                'phar' => 'test.phar',
                'file' => 'test.php',
                'local' => 'src/hello.php',
            ]
        );

        $this->assertSame(1, $exit);
        $this->assertSame(
            "The file \"src/hello.php\" already exists in the Phar.\n",
            $this->getOutput($tester)
        );
    }

    public function testExecuteFileReadError(): void
    {
        $this->preparePhar();

        $root = vfsStream::newDirectory('test');
        $root->addChild(vfsStream::newFile('test.php', 0000));

        vfsStreamWrapper::setRoot($root);

        $tester = $this->getCommandTester();

        try {
            $tester->execute(
                [
                    'command' => 'add',
                    'phar' => 'test.phar',
                    'file' => 'vfs://test/test.php',
                    'local' => 'bin/run',
                    '--main' => true,
                    '--replace' => true,
                ]
            );
        } catch (RuntimeException $exception) {
        }

        $this->assertTrue(isset($exception));
        // @noinspection PhpUndefinedVariableInspection
        $this->assertRegExp(
            '/failed to open stream/',
            $exception->getMessage()
        );
    }

    protected function getCommand(): Command
    {
        return new Add();
    }

    private function preparePhar(): void
    {
        touch('bootstrap.php');

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'bootstrap' => 'bootstrap.php',
                    'compactors' => 'Herrera\\Box\\Compactor\\Php',
                    'main' => 'bin/run',
                    'replacements' => ['name' => 'world'],
                    'stub' => true,
                ]
            )
        );

        $box = Box::create('test.phar');
        $box->addCompactor(new Php());
        $box->setValues(['name' => 'world']);
        $box->addFromString(
            'bin/run',
            <<<'CODE'
#!/usr/bin/run php
<?php

require __DIR__ . '/../src/hello.php';
CODE
        );
        $box->addFromString(
            'src/hello.php',
            <<<CODE
<?php

/**
 * Just saying hello!
 */
echo "Hello, @name@!\n";
CODE
        );
        $box->getPhar()->setStub(
            StubGenerator::create()
                ->index('bin/run')
                ->generate()
        );

        unset($box);
    }
}
