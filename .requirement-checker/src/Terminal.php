<?php

namespace _HumbugBox5af565a878e76\KevinGH\RequirementChecker;

/**
@license
*/
class Terminal
{
    private static $width;
    private static $height;
    /**
    @return
    */
    public function getWidth()
    {
        $width = \getenv('COLUMNS');
        if (\false !== $width) {
            return (int) \trim($width);
        }
        if (null === self::$width) {
            self::initDimensions();
        }
        return self::$width ?: 80;
    }
    /**
    @return
    */
    public function getHeight()
    {
        $height = \getenv('LINES');
        if (\false !== $height) {
            return (int) \trim($height);
        }
        if (null === self::$height) {
            self::initDimensions();
        }
        return self::$height ?: 50;
    }
    private static function initDimensions()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            if (\preg_match('/^(\\d+)x(\\d+)(?: \\((\\d+)x(\\d+)\\))?$/', \trim(\getenv('ANSICON')), $matches)) {
                self::$width = (int) $matches[1];
                self::$height = isset($matches[4]) ? (int) $matches[4] : (int) $matches[2];
            } elseif (null !== ($dimensions = self::getConsoleMode())) {
                self::$width = (int) $dimensions[0];
                self::$height = (int) $dimensions[1];
            }
        } elseif ($sttyString = self::getSttyColumns()) {
            if (\preg_match('/rows.(\\d+);.columns.(\\d+);/i', $sttyString, $matches)) {
                self::$width = (int) $matches[2];
                self::$height = (int) $matches[1];
            } elseif (\preg_match('/;.(\\d+).rows;.(\\d+).columns/i', $sttyString, $matches)) {
                self::$width = (int) $matches[2];
                self::$height = (int) $matches[1];
            }
        }
    }
    /**
    @return
    */
    private static function getConsoleMode()
    {
        if (!\function_exists('proc_open')) {
            return;
        }
        $descriptorspec = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
        $process = \proc_open('mode CON', $descriptorspec, $pipes, null, null, array('suppress_errors' => \true));
        if (\is_resource($process)) {
            $info = \stream_get_contents($pipes[1]);
            \fclose($pipes[1]);
            \fclose($pipes[2]);
            \proc_close($process);
            if (\preg_match('/--------+\\r?\\n.+?(\\d+)\\r?\\n.+?(\\d+)\\r?\\n/', $info, $matches)) {
                return array((int) $matches[2], (int) $matches[1]);
            }
        }
    }
    /**
    @return
    */
    private static function getSttyColumns()
    {
        if (!\function_exists('proc_open')) {
            return;
        }
        $descriptorspec = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
        $process = \proc_open('stty -a | grep columns', $descriptorspec, $pipes, null, null, array('suppress_errors' => \true));
        if (\is_resource($process)) {
            $info = \stream_get_contents($pipes[1]);
            \fclose($pipes[1]);
            \fclose($pipes[2]);
            \proc_close($process);
            return $info;
        }
        return null;
    }
}
