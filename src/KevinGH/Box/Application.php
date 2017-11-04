<?php

namespace KevinGH\Box;

use ErrorException;
use KevinGH\Amend;
use KevinGH\Box\Command;
use KevinGH\Box\Helper;
use Symfony\Component\Console\Application as Base;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Sets up the application.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Application extends Base
{
    /**
     * @override
     */
    public function __construct($name = 'Box', $version = '@git-version@')
    {
        // convert errors to exceptions
        set_error_handler(
            function ($code, $message, $file, $line) {
                if (error_reporting() & $code) {
                    throw new ErrorException($message, 0, $code, $file, $line);
                }
                // @codeCoverageIgnoreStart
            }
            // @codeCoverageIgnoreEnd
        );

        parent::__construct($name, $version);
    }

    /**
     * @override
     */
    public function getLongVersion()
    {
        if (('@' . 'git-version@') !== $this->getVersion()) {
            return sprintf(
                '<info>%s</info> version <comment>%s</comment> build <comment>%s</comment>',
                $this->getName(),
                $this->getVersion(),
                '@git-commit@'
            );
        }

        return '<info>' . $this->getName() . '</info> (repo)';
    }

    /**
     * @override
     */
    public function run(
        InputInterface $input = null,
        OutputInterface $output = null
    ) {
        $output = $output ? : new ConsoleOutput();

        $output->getFormatter()->setStyle(
            'error',
            new OutputFormatterStyle('red')
        );

        $output->getFormatter()->setStyle(
            'question',
            new OutputFormatterStyle('cyan')
        );

        return parent::run($input, $output);
    }

    /**
     * @override
     */
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();

        if (extension_loaded('phar')) {
            $commands[] = new Command\Add();
            $commands[] = new Command\Build();
            $commands[] = new Command\Extract();
            $commands[] = new Command\Info();
            $commands[] = new Command\Remove();
        }

        $commands[] = new Command\Key\Create();
        $commands[] = new Command\Key\Extract();
        $commands[] = new Command\Validate();
        $commands[] = new Command\Verify();

        if (('@' . 'git-version@') !== $this->getVersion()) {
            $command = new Amend\Command('update');
            $command->setManifestUri('@manifest_url@');

            $commands[] = $command;
        }

        return $commands;
    }

    /**
     * @override
     */
    protected function getDefaultHelperSet()
    {
        $helperSet = parent::getDefaultHelperSet();
        $helperSet->set(new Helper\ConfigurationHelper());
        $helperSet->set(new Helper\PhpSecLibHelper());

        if (('@' . 'git-version@') !== $this->getVersion()) {
            $helperSet->set(new Amend\Helper());
        }

        return $helperSet;
    }
}
