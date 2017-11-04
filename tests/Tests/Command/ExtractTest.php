<?php

namespace KevinGH\Box\Tests\Command;

use KevinGH\Box\Command\Extract;
use KevinGH\Box\Test\CommandTestCase;
use Phar;
use Symfony\Component\Console\Output\OutputInterface;

class ExtractTest extends CommandTestCase
{
    public function testExecute()
    {
        $rand = 'test-' . rand() . '.phar';
        $phar = new Phar($rand);
        $phar->addFromString('a/b/c/d.php', '<?php echo "Hello!";');
        $phar->addFromString('a/b/c/e.php', '<?php echo "Goodbye!";');

        unset($phar);

        $tester = $this->getTester();
        $tester->execute(
            array(
                'command' => 'extract',
                'phar' => $rand,
                '--pick' => array('a/b/c/e.php'),
                '--out' => 'extracted'
            ),
            array(
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE
            )
        );

        $expected = <<<OUTPUT
Extracting files from the Phar...
Done.

OUTPUT;

        $this->assertEquals(
            '<?php echo "Goodbye!";',
            file_get_contents('extracted/a/b/c/e.php')
        );
        $this->assertEquals($expected, $this->getOutput($tester));
    }

    public function testExecuteAlternate()
    {
        $rand = 'test-' . rand() . '.phar';
        $phar = new Phar($rand);
        $phar->addFromString('a/b/c/d.php', '<?php echo "Hello!";');
        $phar->addFromString('a/b/c/e.php', '<?php echo "Goodbye!";');

        unset($phar);

        $tester = $this->getTester();
        $tester->execute(
            array(
                'command' => 'extract',
                'phar' => $rand
            ),
            array(
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE
            )
        );

        $expected = <<<OUTPUT
Extracting files from the Phar...
Done.

OUTPUT;

        $this->assertEquals(
            '<?php echo "Hello!";',
            file_get_contents("$rand-contents/a/b/c/d.php")
        );
        $this->assertEquals(
            '<?php echo "Goodbye!";',
            file_get_contents("$rand-contents/a/b/c/e.php")
        );
        $this->assertEquals($expected, $this->getOutput($tester));
    }

    public function testExecuteNotExist()
    {
        $tester = $this->getTester();
        $tester->execute(
            array(
                'command' => 'extract',
                'phar' => 'test.phar'
            ),
            array(
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE
            )
        );

        $expected = <<<OUTPUT
Extracting files from the Phar...
The path "test.phar" is not a file or does not exist.

OUTPUT;

        $this->assertEquals($expected, $this->getOutput($tester));
    }

    protected function getCommand()
    {
        return new Extract();
    }
}
