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

namespace KevinGH\Box;

use ErrorException;
use KevinGH\Amend;
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

        if (('@'.'git-version@') !== $this->getVersion()) {
            $command = new Amend\Command('update');
            $command->setManifestUri('@manifest_url@');

            $commands[] = $command;
        }

        return $commands;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultHelperSet(): HelperSet
    {
        $helperSet = parent::getDefaultHelperSet();
        $helperSet->set(new Helper\ConfigurationHelper());
        $helperSet->set(new Helper\PhpSecLibHelper());

        if (('@'.'git-version@') !== $this->getVersion()) {
            $helperSet->set(new Amend\Helper());
        }

        return $helperSet;
    }
}
