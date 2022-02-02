<?php

namespace HumbugBox3141\KevinGH\RequirementChecker;

final class IO
{
    const VERBOSITY_QUIET = 16;
    const VERBOSITY_NORMAL = 32;
    const VERBOSITY_VERBOSE = 64;
    const VERBOSITY_VERY_VERBOSE = 128;
    const VERBOSITY_DEBUG = 256;
    private $interactive;
    private $verbosity = self::VERBOSITY_NORMAL;
    private $colorSupport;
    private $options;
    public function __construct()
    {
        $this->options = \implode(' ', $_SERVER['argv']);
        $shellVerbosity = $this->configureVerbosity();
        $this->interactive = $this->checkInteractivity($shellVerbosity);
        $this->colorSupport = $this->checkColorSupport();
    }
    public function isInteractive()
    {
        return $this->interactive;
    }
    public function getVerbosity()
    {
        return $this->verbosity;
    }
    public function hasColorSupport()
    {
        return $this->colorSupport;
    }
    public function hasParameter($values)
    {
        $values = (array) $values;
        foreach ($values as $value) {
            $regexp = \sprintf('/\\s%s\\b/', \str_replace(' ', '\\s+', \preg_quote($value, '/')));
            if (1 === \preg_match($regexp, $this->options)) {
                return \true;
            }
        }
        return \false;
    }
    private function checkInteractivity($shellVerbosity)
    {
        if (-1 === $shellVerbosity) {
            return \false;
        }
        if (\true === $this->hasParameter(array('--no-interaction', '-n'))) {
            return \false;
        }
        if (\function_exists('posix_isatty') && !@\posix_isatty(\STDOUT) && \false === \getenv('SHELL_INTERACTIVE')) {
            return \false;
        }
        return \true;
    }
    private function configureVerbosity()
    {
        switch ($shellVerbosity = (int) \getenv('SHELL_VERBOSITY')) {
            case -1:
                $this->verbosity = self::VERBOSITY_QUIET;
                break;
            case 1:
                $this->verbosity = self::VERBOSITY_VERBOSE;
                break;
            case 2:
                $this->verbosity = self::VERBOSITY_VERY_VERBOSE;
                break;
            case 3:
                $this->verbosity = self::VERBOSITY_DEBUG;
                break;
            default:
                $shellVerbosity = 0;
                break;
        }
        if ($this->hasParameter(array('--quiet', '-q'))) {
            $this->verbosity = self::VERBOSITY_QUIET;
            $shellVerbosity = -1;
        } elseif ($this->hasParameter(array('-vvv', '--verbose=3', '--verbose 3'))) {
            $this->verbosity = self::VERBOSITY_DEBUG;
            $shellVerbosity = 3;
        } elseif ($this->hasParameter(array('-vv', '--verbose=2', '--verbose 2'))) {
            $this->verbosity = self::VERBOSITY_VERY_VERBOSE;
            $shellVerbosity = 2;
        } elseif ($this->hasParameter(array('-v', '--verbose=1', '--verbose 1', '--verbose'))) {
            $this->verbosity = self::VERBOSITY_VERBOSE;
            $shellVerbosity = 1;
        }
        return $shellVerbosity;
    }
    private function checkColorSupport()
    {
        if ($this->hasParameter(array('--ansi'))) {
            return \true;
        }
        if ($this->hasParameter(array('--no-ansi'))) {
            return \false;
        }
        if (\DIRECTORY_SEPARATOR === '\\') {
            return \function_exists('sapi_windows_vt100_support') && \sapi_windows_vt100_support(\STDOUT) || \false !== \getenv('ANSICON') || 'ON' === \getenv('ConEmuANSI') || 'xterm' === \getenv('TERM');
        }
        if (\function_exists('stream_isatty')) {
            return \stream_isatty(\STDOUT);
        }
        if (\function_exists('posix_isatty')) {
            return \posix_isatty(\STDOUT);
        }
        $stat = \fstat(\STDOUT);
        return $stat ? 020000 === ($stat['mode'] & 0170000) : \false;
    }
}
