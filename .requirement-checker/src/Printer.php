<?php

namespace HumbugBox383\KevinGH\RequirementChecker;

final class Printer
{
    private $styles = array('reset' => "\33[0m", 'red' => "\33[31m", 'green' => "\33[32m", 'yellow' => "\33[33m", 'title' => "\33[33m", 'error' => "\33[37;41m", 'success' => "\33[30;42m");
    private $verbosity;
    private $supportColors;
    private $width;
    public function __construct($verbosity, $supportColors, $width = null)
    {
        if (null === $width) {
            $terminal = new \HumbugBox383\KevinGH\RequirementChecker\Terminal();
            $width = \min($terminal->getWidth(), 80);
        }
        $this->verbosity = $verbosity;
        $this->supportColors = $supportColors;
        $this->width = $width;
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
    public function getRequirementErrorMessage(\HumbugBox383\KevinGH\RequirementChecker\Requirement $requirement)
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
        $message = $prefix . \trim($message);
        $lines = array();
        $remainingMessage = $message;
        while ($remainingMessage !== '') {
            $wrapped = \wordwrap($remainingMessage, $this->width - 3, '¬');
            $exploded = \explode('¬', $wrapped);
            $line = $exploded[0];
            $remainingMessage = \ltrim(\substr($remainingMessage, \strlen($line)));
            if ($remainingMessage !== '') {
                $remainingMessage = \str_repeat(' ', \strlen($prefix)) . $remainingMessage;
            }
            $lines[] = \str_pad($line, $this->width, ' ', \STR_PAD_RIGHT);
        }
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
