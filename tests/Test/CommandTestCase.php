<?php

namespace KevinGH\Box\Test;

use Herrera\PHPUnit\TestCase;
use KevinGH\Box\Helper;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Makes it easier to test Box commands.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
abstract class CommandTestCase extends TestCase
{
    /**
     * The application.
     *
     * @var Application
     */
    protected $app;

    /**
     * The actual current working directory.
     *
     * @var string
     */
    protected $cwd;

    /**
     * The test current working directory.
     *
     * @var string
     */
    protected $dir;

    /**
     * The name of the command.
     *
     * @var string
     */
    private $name;

    /**
     * Returns the command to be tested.
     *
     * @return Command The command.
     */
    abstract protected function getCommand();

    /**
     * Returns the output for the tester.
     *
     * @param CommandTester $tester The tester.
     *
     * @return string The output.
     */
    protected function getOutput(CommandTester $tester)
    {
        /** @var $output StreamOutput */
        $output = $tester->getOutput();
        $stream = $output->getStream();
        $string = '';

        rewind($stream);

        while (false === feof($stream)) {
            $string .= fgets($stream);
        }

        $string = preg_replace(
            array(
                '/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/',
                '/[\x03|\x1a]/'
            ),
            array('', '', ''),
            $string
        );

        return str_replace(PHP_EOL, "\n", $string);
    }

    /**
     * Returns the tester for the command.
     *
     * @return CommandTester The tester.
     */
    protected function getTester()
    {
        return new CommandTester($this->app->get($this->name));
    }

    /**
     * Restore the current working directory.
     */
    protected function tearDown()
    {
        chdir($this->cwd);

        parent::tearDown();
    }

    /**
     * Creates the application.
     */
    protected function setUp()
    {
        $this->cwd = getcwd();
        $this->dir = $this->createDir();

        chdir($this->dir);

        $this->app = new Application();
        $this->app->getHelperSet()->set(new Helper\ConfigurationHelper());
        $this->app->getHelperSet()->set(new Helper\PhpSecLibHelper());

        $command = $this->getCommand();
        $this->name = $command->getName();

        $this->app->add($command);
    }
}
