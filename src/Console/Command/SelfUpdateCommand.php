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

namespace KevinGH\Box\Console\Command;

use Humbug\SelfUpdate\Updater;
use KevinGH\Box\Console\Logger\UpdateConsoleLogger;
use PHAR;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

final class SelfUpdateCommand extends Command
{
    private const REMOTE_FILENAME = 'box.phar';
    private const STABILITY_STABLE = 'stable';
    private const PACKAGIST_PACKAGE_NAME = 'humbug/box';
    private const ROLLBACK_OPT = 'rollback';
    private const CHECK_OPT = 'check';
    private $updater;

    /**
     * @var string
     */
    private $version;

    /**
     * @var UpdateConsoleLogger
     */
    private $logger;

    /**
     * {@inheritdoc}
     */
    public function __construct(Updater $updater)
    {
        parent::__construct();

        $this->updater = $updater;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('self-update')
            ->setDescription(sprintf(
                'Update %s to most recent stable build.',
                $this->getLocalPharName()
            ))
            ->addOption(
                self::ROLLBACK_OPT,
                'r',
                InputOption::VALUE_NONE,
                'Rollback to previous version of PHP-Scoper if available on filesystem.'
            )
            ->addOption(
                self::CHECK_OPT,
                'c',
                InputOption::VALUE_NONE,
                'Checks whether an update is available.'
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger = new UpdateConsoleLogger(
            new SymfonyStyle($input, $output)
        );

        $this->version = $this->getApplication()->getVersion();

        $this->configureUpdater();

        if ($input->getOption('rollback')) {
            $this->rollback();

            return 0;
        }

        if ($input->getOption('check')) {
            $this->printAvailableUpdates();

            return 0;
        }

        $this->update();

        return 0;
    }

    private function configureUpdater(): void
    {
        $this->updater->setStrategy(Updater::STRATEGY_GITHUB);
        $this->updater->getStrategy()->setPackageName(self::PACKAGIST_PACKAGE_NAME);
        $this->updater->getStrategy()->setPharName(self::REMOTE_FILENAME);
        $this->updater->getStrategy()->setCurrentLocalVersion($this->version);
    }

    private function update(): void
    {
        $this->logger->startUpdating();

        try {
            $result = $this->updater->update();

            $newVersion = $this->updater->getNewVersion();
            $oldVersion = $this->updater->getOldVersion();

            if ($result) {
                $this->logger->updateSuccess($newVersion, $oldVersion);
            } else {
                $this->logger->updateNotNeeded($oldVersion);
            }
        } catch (Throwable $throwable) {
            $this->logger->error($throwable);

            throw $throwable;
        }
    }

    private function rollback(): void
    {
        try {
            $result = $this->updater->rollback();

            if ($result) {
                $this->logger->rollbackSuccess();
            } else {
                $this->logger->rollbackFail();
            }
        } catch (Throwable $throwable) {
            $this->logger->error($throwable);

            throw $throwable;
        }
    }

    private function printAvailableUpdates(): void
    {
        $this->logger->printLocalVersion($this->version);

        $this->printCurrentStableVersion();
    }

    private function printCurrentStableVersion(): void
    {
        $stability = self::STABILITY_STABLE;

        try {
            if ($this->updater->hasUpdate()) {
                $this->logger->printRemoteVersion(
                    $stability,
                    $this->updater->getNewVersion()
                );
            } elseif (false == $this->updater->getNewVersion()) {
                $this->logger->noNewRemoteVersions($stability);
            } else {
                $this->logger->currentVersionInstalled($stability);
            }
        } catch (Throwable $throwable) {
            $this->logger->error($throwable);

            throw $throwable;
        }
    }

    private function getLocalPharName(): string
    {
        return basename(PHAR::running());
    }
}
