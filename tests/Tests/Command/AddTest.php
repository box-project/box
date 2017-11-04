<?php

namespace KevinGH\Box\Tests\Command;

use Herrera\Box\Box;
use Herrera\Box\Compactor\Php;
use Herrera\Box\StubGenerator;
use KevinGH\Box\Command\Add;
use KevinGH\Box\Test\CommandTestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

class AddTest extends CommandTestCase
{
    public function testExecute()
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

        $tester = $this->getTester();
        $tester->execute(
            array(
                'command' => 'add',
                'phar' => 'test.phar',
                'file' => 'goodbye.php',
                'local' => 'src/hello.php',
                '--replace' => true
            ),
            array(
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE
            )
        );

        $dir = $this->dir . DIRECTORY_SEPARATOR;
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

        $this->assertEquals($expected, $this->getOutput($tester));

        $this->assertEquals(
            'Goodbye, world!',
            trim(exec('php test.phar'))
        );
    }

    public function testExecuteBinary()
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

        $tester = $this->getTester();
        $tester->execute(
            array(
                'command' => 'add',
                'phar' => 'test.phar',
                'file' => 'goodbye.php',
                'local' => 'src/hello.php',
                '--binary' => true,
                '--replace' => true
            ),
            array(
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE
            )
        );

        $dir = $this->dir . DIRECTORY_SEPARATOR;
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

        $this->assertEquals($expected, $this->getOutput($tester));

        $this->assertEquals(
            'Goodbye, @name@!',
            trim(exec('php test.phar'))
        );
    }

    public function testExecuteStub()
    {
        $this->preparePhar();

        file_put_contents(
            'stub.php',
            '<?php echo "Hello, stub!\n"; __HALT_COMPILER();'
        );

        $tester = $this->getTester();
        $tester->execute(
            array(
                'command' => 'add',
                'phar' => 'test.phar',
                'file' => 'stub.php',
                '--stub' => true
            ),
            array(
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE
            )
        );

        $dir = $this->dir . DIRECTORY_SEPARATOR;
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

        $this->assertEquals($expected, $this->getOutput($tester));
        $this->assertEquals(
            'Hello, stub!',
            trim(exec('php test.phar'))
        );
    }

    public function testExecuteMain()
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

        $tester = $this->getTester();
        $tester->execute(
            array(
                'command' => 'add',
                'phar' => 'test.phar',
                'file' => 'main.php',
                'local' => 'bin/run',
                '--main' => true,
                '--replace' => true
            ),
            array(
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE
            )
        );

        $dir = $this->dir . DIRECTORY_SEPARATOR;
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

        $this->assertEquals($expected, $this->getOutput($tester));
        $this->assertEquals(
            'Sup, world!',
            trim(exec('php test.phar'))
        );
    }

    public function testExecuteMissingLocal()
    {
        $tester = $this->getTester();
        $exit = $tester->execute(
            array(
                'command' => 'add',
                'phar' => 'test.phar',
                'file' => 'test.php'
            )
        );

        $this->assertEquals(1, $exit);
        $this->assertEquals(
            "The local argument is required.\n",
            $this->getOutput($tester)
        );
    }

    public function testExecutePharNotExist()
    {
        file_put_contents('box.json', '{}');

        $tester = $this->getTester();
        $exit = $tester->execute(
            array(
                'command' => 'add',
                'phar' => 'test.phar',
                'file' => 'test.php',
                'local' => 'test.php'
            )
        );

        $this->assertEquals(1, $exit);
        $this->assertEquals(
            "The path \"test.phar\" is not a file or does not exist.\n",
            $this->getOutput($tester)
        );
    }

    public function testExecuteFileNotExist()
    {
        file_put_contents('box.json', '{}');
        touch('test.phar');

        $tester = $this->getTester();
        $exit = $tester->execute(
            array(
                'command' => 'add',
                'phar' => 'test.phar',
                'file' => 'test.php',
                'local' => 'test.php'
            )
        );

        $this->assertEquals(1, $exit);
        $this->assertEquals(
            "The path \"test.php\" is not a file or does not exist.\n",
            $this->getOutput($tester)
        );
    }

    public function testExecuteExists()
    {
        $this->preparePhar();

        touch('test.php');

        $tester = $this->getTester();
        $exit = $tester->execute(
            array(
                'command' => 'add',
                'phar' => 'test.phar',
                'file' => 'test.php',
                'local' => 'src/hello.php'
            )
        );

        $this->assertEquals(1, $exit);
        $this->assertEquals(
            "The file \"src/hello.php\" already exists in the Phar.\n",
            $this->getOutput($tester)
        );
    }

    public function testExecuteFileReadError()
    {
        $this->preparePhar();

        $root = vfsStream::newDirectory('test');
        $root->addChild(vfsStream::newFile('test.php', 0000));

        vfsStreamWrapper::setRoot($root);

        $tester = $this->getTester();

        try {
            $tester->execute(
                array(
                    'command' => 'add',
                    'phar' => 'test.phar',
                    'file' => 'vfs://test/test.php',
                    'local' => 'bin/run',
                    '--main' => true,
                    '--replace' => true
                )
            );
        } catch (RuntimeException $exception) {
        }

        $this->assertTrue(isset($exception));
        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertRegExp(
            '/failed to open stream/',
            $exception->getMessage()
        );
    }

    protected function getCommand()
    {
        return new Add();
    }

    private function preparePhar()
    {
        touch('bootstrap.php');

        file_put_contents(
            'box.json',
            json_encode(
                array(
                    'bootstrap' => 'bootstrap.php',
                    'compactors' => 'Herrera\\Box\\Compactor\\Php',
                    'main' => 'bin/run',
                    'replacements' => array('name' => 'world'),
                    'stub' => true
                )
            )
        );

        $box = Box::create('test.phar');
        $box->addCompactor(new Php());
        $box->setValues(array('name' => 'world'));
        $box->addFromString(
            'bin/run',
            <<<CODE
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
