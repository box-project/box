<?php

namespace HumbugBox3141\KevinGH\RequirementChecker;

final class Printer
{
    private $styles = array('reset' => "\x1b[0m", 'red' => "\x1b[31m", 'green' => "\x1b[32m", 'yellow' => "\x1b[33m", 'title' => "\x1b[33m", 'error' => "\x1b[37;41m", 'success' => "\x1b[30;42m");
    private $verbosity;
    private $supportColors;
    private $width;
    public function __construct($verbosity, $supportColors, $width = null)
    {
        if (null === $width) {
            $terminal = new Terminal();
            $width = $terminal->getWidth();
        }
        $this->verbosity = $verbosity;
        $this->supportColors = $supportColors;
        $this->width = $width ?: 80;
    }
    public function getVerbosity()
    {
        return $this->verbosity;
    }
    public function setVerbosity($verbosity)
    {
        $this->verbosity = $verbosity;
    }
    public function title($title, $verbosity, $style = null)
    {
        if (null === $style) {
            $style = 'title';
        }
        $this->printvln('', $verbosity, $style);
        $this->printvln($title, $verbosity, $style);
        $this->printvln(\str_repeat('=', \min(\strlen($title), $this->width)), $verbosity, $style);
        $this->printvln('', $verbosity, $style);
    }
    public function getRequirementErrorMessage(Requirement $requirement)
    {
        if ($requirement->isFulfilled()) {
            return null;
        }
        $errorMessage = \wordwrap($requirement->getTestMessage(), $this->width - 3, \PHP_EOL . '   ') . \PHP_EOL;
        return $errorMessage;
    }
    public function block($title, $message, $verbosity, $style = null)
    {
        $prefix = ' [' . $title . '] ';
        $lineLength = $this->width - \strlen($prefix) - 1;
        if ($lineLength < 0) {
            $lineLength = 0;
        }
        $message = $prefix . \trim($message);
        $lines = array();
        $remainingMessage = $message;
        $wrapped = \wordwrap($remainingMessage, $lineLength, '¬');
        $wrapped = \explode('¬', $wrapped);
        do {
            $line = \array_shift($wrapped);
            if ($lines && $lineLength > 0) {
                $line = \str_repeat(' ', \strlen($prefix)) . \ltrim($line);
            }
            $lines[] = \str_pad($line, $this->width, ' ', \STR_PAD_RIGHT);
        } while (\count($wrapped));
        $this->printvln('', $verbosity);
        $this->printvln(\str_repeat(' ', $this->width), $verbosity, $style);
        foreach ($lines as $line) {
            $this->printvln($line, $verbosity, $style);
        }
        $this->printv(\str_repeat(' ', $this->width), $verbosity, $style);
        $this->printvln('', $verbosity);
    }
    public function printvln($message, $verbosity, $style = null)
    {
        $this->printv($message, $verbosity, $style);
        $this->printv(\PHP_EOL, $verbosity, null);
    }
    public function printv($message, $verbosity, $style = null)
    {
        if ($verbosity > $this->verbosity) {
            return;
        }
        $message = \wordwrap($message, $this->width);
        $message = \sprintf('%s%s%s', $this->supportColors && isset($this->styles[$style]) ? $this->styles[$style] : '', $message, $this->supportColors ? $this->styles['reset'] : '');
        echo $message;
    }
}
