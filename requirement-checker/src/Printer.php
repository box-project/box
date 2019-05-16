<?php

/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace KevinGH\RequirementChecker;

/**
 * @private
 */
final class Printer
{
    private $styles = array(
        'reset' => "\033[0m",
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'title' => "\033[33m",
        'error' => "\033[37;41m",
        'success' => "\033[30;42m",
    );
    private $verbosity;
    private $supportColors;
    private $width;

    /**
     * @param int      $verbosity
     * @param bool     $supportColors
     * @param null|int $width
     */
    public function __construct($verbosity, $supportColors, $width = null)
    {
        if (null === $width) {
            $terminal = new Terminal();
            $width = min($terminal->getWidth(), 80);
        }

        $this->verbosity = $verbosity;
        $this->supportColors = $supportColors;
        $this->width = $width;
    }

    /**
     * @return int
     */
    public function getVerbosity()
    {
        return $this->verbosity;
    }

    /**
     * @param int $verbosity
     */
    public function setVerbosity($verbosity)
    {
        $this->verbosity = $verbosity;
    }

    /**
     * @param string      $title
     * @param int         $verbosity
     * @param null|string $style
     */
    public function title($title, $verbosity, $style = null)
    {
        if (null === $style) {
            $style = 'title';
        }

        $this->printvln('', $verbosity, $style);
        $this->printvln($title, $verbosity, $style);
        $this->printvln(
            str_repeat(
                '=',
                min(\strlen($title), $this->width)
            ),
            $verbosity,
            $style
        );
        $this->printvln('', $verbosity, $style);
    }

    /**
     * @param Requirement $requirement
     *
     * @return null|string
     */
    public function getRequirementErrorMessage(Requirement $requirement)
    {
        if ($requirement->isFulfilled()) {
            return null;
        }

        $errorMessage = wordwrap($requirement->getTestMessage(), $this->width - 3, PHP_EOL.'   ').PHP_EOL;

        return $errorMessage;
    }

    /**
     * @param string      $title
     * @param string      $message
     * @param int         $verbosity
     * @param null|string $style
     */
    public function block($title, $message, $verbosity, $style = null)
    {
        $prefix = ' ['.$title.'] ';
        $message = $prefix.trim($message);

        $lines = array();

        $remainingMessage = $message;

        while ($remainingMessage !== '') {
            $wrapped = wordwrap($remainingMessage, $this->width - 3, '¬');
            $exploded = explode('¬', $wrapped);
            $line = $exploded[0];
            $remainingMessage = ltrim(substr($remainingMessage, \strlen($line)));

            if ($remainingMessage !== '') {
                $remainingMessage = str_repeat(' ', \strlen($prefix)).$remainingMessage;
            }

            $lines[] = str_pad($line, $this->width, ' ', STR_PAD_RIGHT);
        }

        $this->printvln('', $verbosity);
        $this->printvln(str_repeat(' ', $this->width), $verbosity, $style);
        foreach ($lines as $line) {
            $this->printvln($line, $verbosity, $style);
        }
        $this->printv(str_repeat(' ', $this->width), $verbosity, $style);
        $this->printvln('', $verbosity);
    }

    /**
     * @param string      $message
     * @param int         $verbosity
     * @param null|string $style
     */
    public function printvln($message, $verbosity, $style = null)
    {
        $this->printv($message, $verbosity, $style);
        $this->printv(PHP_EOL, $verbosity, null);
    }

    /**
     * @param string      $message
     * @param int         $verbosity
     * @param null|string $style
     */
    public function printv($message, $verbosity, $style = null)
    {
        if ($verbosity > $this->verbosity) {
            return;
        }

        $message = wordwrap($message, $this->width);

        $message = sprintf(
            '%s%s%s',
            $this->supportColors && isset($this->styles[$style]) ? $this->styles[$style] : '',
            $message,
            $this->supportColors ? $this->styles['reset'] : ''
        );

        echo $message;
    }
}
