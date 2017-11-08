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
     * @return bool TRUE if verbose, FALSE if not
     *
     * @deprecated
     */
    protected function isVerbose()
    {
        return OutputInterface::VERBOSITY_VERBOSE <= $this->output->getVerbosity();
    }

    /**
     * Outputs a message with a colored prefix.
     *
     * @param string $prefix  the prefix
     * @param string $message the message
     *
     * @deprecated
     */
    protected function putln($prefix, $message): void
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
     *
     * @param mixed $message
     * @param mixed $newline
     * @param mixed $type
     *
     * @deprecated
     */
    protected function verbose($message, $newline = false, $type = 0): void
    {
        if ($this->output->isVerbose()) {
            $this->output->write($message, $newline, $type);
        }
    }

    /**
     * Writes the message only when verbosity is set to VERBOSITY_VERBOSE.
     *
     * @see OutputInterface#writeln
     *
     * @param mixed $message
     * @param mixed $type
     *
     * @deprecated
     */
    protected function verboseln($message, $type = 0): void
    {
        $this->verbose($message, true, $type);
    }
}
