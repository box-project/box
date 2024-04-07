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

use Fidry\Console\Application\Application as FidryApplication;
use KevinGH\Box\Console\Command\Check\CheckSignatureCommand as CheckSignature;
use KevinGH\Box\Console\Command\CompileCommand;
use KevinGH\Box\Console\Command\Composer\ComposerCheckVersionCommand;
use KevinGH\Box\Console\Command\Composer\ComposerVendorDirCommand;
use KevinGH\Box\Console\Command\DiffCommand;
use KevinGH\Box\Console\Command\ExtractCommand;
use KevinGH\Box\Console\Command\GenerateDockerFileCommand;
use KevinGH\Box\Console\Command\Info\InfoSignatureCommand as InfoSignature;
use KevinGH\Box\Console\Command\InfoCommand;
use KevinGH\Box\Console\Command\NamespaceCommand;
use KevinGH\Box\Console\Command\ProcessCommand;
use KevinGH\Box\Console\Command\ValidateCommand;
use KevinGH\Box\Console\Command\VerifyCommand;
use KevinGH\Box\RequirementChecker\AppRequirementsFactory;
use KevinGH\Box\RequirementChecker\RequirementsDumper;
use function KevinGH\Box\get_box_version;
use function sprintf;
use function trim;

/**
 * @private
 */
final class Application implements FidryApplication
{
    private readonly string $version;
    private readonly string $releaseDate;
    private string $header;

    public function __construct(
        private readonly string $name = 'Box',
        ?string $version = null,
        string $releaseDate = '@release-date@',
        private readonly bool $autoExit = true,
        private readonly bool $catchExceptions = true,
    ) {
        $this->version = $version ?? get_box_version();
        $this->releaseDate = !str_contains($releaseDate, '@') ? $releaseDate : '';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getLongVersion(): string
    {
        return trim(
            sprintf(
                '<info>%s</info> version <comment>%s</comment> %s',
                $this->getName(),
                $this->getVersion(),
                $this->releaseDate,
            ),
        );
    }

    public function getHelp(): string
    {
        return $this->getHeader();
    }

    public function getHeader(): string
    {
        if (!isset($this->header)) {
            $this->header = Logo::LOGO_ASCII.$this->getLongVersion();
        }

        return $this->header;
    }

    public function getCommands(): array
    {
        return [
            new ComposerCheckVersionCommand(),
            new ComposerVendorDirCommand(),
            new CompileCommand(
                $this->getHeader(),
                new RequirementsDumper(
                    new AppRequirementsFactory(),
                ),
            ),
            new DiffCommand(),
            new InfoCommand(),
            new InfoCommand('info:general'),
            new InfoSignature(),
            new CheckSignature(),
            new ProcessCommand(),
            new ExtractCommand(),
            new ValidateCommand(),
            new VerifyCommand(),
            new GenerateDockerFileCommand(),
            new NamespaceCommand(),
        ];
    }

    public function getDefaultCommand(): string
    {
        return 'list';
    }

    public function isAutoExitEnabled(): bool
    {
        return $this->autoExit;
    }

    public function areExceptionsCaught(): bool
    {
        return $this->catchExceptions;
    }
}
