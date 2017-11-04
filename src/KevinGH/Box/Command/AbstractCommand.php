<?php

namespace KevinGH\Box\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provides common functionality to all commands.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
abstract class AbstractCommand extends Command
{
    /**
     * The output handler.
     *
     * @var OutputInterface
     */
    private $output;

    /**
     * @override
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        return parent::run($input, $output);
    }

    /**
     * Checks if the output handler is verbose.
     *
     * @return boolean TRUE if verbose, FALSE if not.
     */
    protected function isVerbose()
    {
        return (OutputInterface::VERBOSITY_VERBOSE <= $this->output->getVerbosity());
    }

    /**
     * Outputs a message with a colored prefix.
     *
     * @param string $prefix  The prefix.
     * @param string $message The message.
     */
    protected function putln($prefix, $message)
    {
        switch ($prefix) {
            case '!':
                $prefix = "<error>$prefix</error>";
                break;
            case '*':
                $prefix = "<info>$prefix</info>";
                break;
            case '?':
                $prefix = "<comment>$prefix</comment>";
                break;
            case '-':
            case '+':
                $prefix = "  <comment>$prefix</comment>";
                break;
            case '>':
                $prefix = "    <comment>$prefix</comment>";
                break;
        }

        $this->verboseln("$prefix $message");
    }

    /**
     * Writes the message only when verbosity is set to VERBOSITY_VERBOSE.
     *
     * @see OutputInterface#write
     */
    protected function verbose($message, $newline = false, $type = 0)
    {
        if ($this->isVerbose()) {
            $this->output->write($message, $newline, $type);
        }
    }

    /**
     * Writes the message only when verbosity is set to VERBOSITY_VERBOSE
     *
     * @see OutputInterface#writeln
     */
    protected function verboseln($message, $type = 0)
    {
        $this->verbose($message, true, $type);
    }
}
