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

namespace KevinGH\Box\Console;

use ErrorException;
use Humbug\SelfUpdate\Exception\RuntimeException as SelfUpdateRuntimeException;
use Humbug\SelfUpdate\Updater;
use KevinGH\Box\Console\Command;
use KevinGH\Box\Console\Command\SelfUpdateCommand;
use KevinGH\Box\Helper;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class Application extends SymfonyApplication
{
    private const LOGO = <<<'ASCII'

    ____            
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <  
/_____/\____/_/|_|  
                    


ASCII;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $name = 'Box', string $version = '@git-version@')
    {
        // convert errors to exceptions
        set_error_handler(
            function ($code, $message, $file, $line): void {
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
     * {@inheritdoc}
     */
    public function getLongVersion()
    {
        if (('@'.'git-version@') !== $this->getVersion()) {
            return sprintf(
                '<info>%s</info> version <comment>%s</comment> build <comment>%s</comment>',
                $this->getName(),
                $this->getVersion(),
                '@git-commit@'
            );
        }

        return '<info>'.$this->getName().'</info> (repo)';
    }

    /**
     * @override
     */
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        $output = $output ?: new ConsoleOutput();

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
     * {@inheritdoc}
     */
    public function getHelp(): string
    {
        return self::LOGO.parent::getHelp();
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultCommands(): array
    {
        $commands = parent::getDefaultCommands();

        $commands[] = new Command\Build();
        $commands[] = new Command\Info();
        $commands[] = new Command\Validate();
        $commands[] = new Command\Verify();

        if ('phar:' === substr(__FILE__, 0, 5)) {
            try {
                $updater = new Updater();
            } catch (SelfUpdateRuntimeException $e) {
                // Allow E2E testing of unsigned phar
                $updater = new Updater(null, false);
            }
            $commands[] = new SelfUpdateCommand($updater);
        }

        return $commands;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultHelperSet(): HelperSet
    {
        $helperSet = parent::getDefaultHelperSet();
        $helperSet->set(new \KevinGH\Box\Console\ConfigurationHelper());

        return $helperSet;
    }
}
