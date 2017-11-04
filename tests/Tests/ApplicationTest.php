<?php

namespace KevinGH\Box\Tests;

use ErrorException;
use Herrera\PHPUnit\TestCase;
use KevinGH\Box\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;

class ApplicationTest extends TestCase
{
    public function testApp()
    {
        $app = new Application();
        $app->setAutoExit(false);

        $input = new ArrayInput(array('--version'));
        $stream = fopen('php://memory', 'w', false);
        $output = new StreamOutput($stream);

        $app->run($input, $output);

        rewind($stream);

        $string = trim(fgets($stream));
        $string = preg_replace(
            array(
                '/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/',
                '/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/',
                '/[\x03|\x1a]/'
            ),
            array('', '', ''),
            $string
        );

        $this->assertEquals('Box (repo)', $string);

        $app->setVersion('1.2.3');

        rewind($stream);

        $app->run($input, $output);

        rewind($stream);

        $string = trim(fgets($stream));
        $string = preg_replace(
            array(
                '/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/',
                '/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/',
                '/[\x03|\x1a]/'
            ),
            array('', '', ''),
            $string
        );

        $this->assertEquals(
            'Box version 1.2.3 build @git-commit@',
            $string
        );

        try {
            trigger_error('Test.', E_USER_WARNING);
        } catch (ErrorException $exception) {
        }

        $this->assertTrue(isset($exception));
    }

    public function testAppNonRepo()
    {
        $app = new Application('Test', '1.2.3');
        $app->setAutoExit(false);

        restore_error_handler();

        $this->assertInstanceOf(
            'KevinGH\\Amend\\Command',
            $app->get('update')
        );
    }
}
