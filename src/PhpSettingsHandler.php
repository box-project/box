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

use Composer\XdebugHandler\Process;
use Composer\XdebugHandler\XdebugHandler;
use Psr\Log\LoggerInterface;
use const PHP_EOL;
use function function_exists;
use function getenv;
use function ini_get;
use function KevinGH\Box\FileSystem\append_to_file;
use function sprintf;
use function trim;

/**
 * @private
 */
final class PhpSettingsHandler extends XdebugHandler
{
    private $logger;

    /**
     * {@inheritdoc}
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct('box', '--ansi');

        $this->setLogger($logger);
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function check(): void
    {
        parent::check();

        if (self::getRestartSettings()) {
            Process::setEnv('PHPRC', XdebugHandler::getRestartSettings()['tmpIni']);
            Process::setEnv('PHP_INI_SCAN_DIR', '');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function requiresRestart($isLoaded): bool
    {
        return null === self::getRestartSettings();
    }

    /**
     * {@inheritdoc}
     */
    protected function restart($command): void
    {
        if (function_exists('ini_get')) {
            // Disable phar.readonly if set
            $this->disablePharReadonly();

            // Bump the memory limit if necessary
            $this->bumpMemoryLimit();
        }

        parent::restart($command);
    }

    private function disablePharReadonly(): void
    {
        if (ini_get('phar.readonly')) {
            append_to_file($this->tmpIni, 'phar.readonly=0'.PHP_EOL);

            $this->logger->debug('Configured `phar.readonly=0`');
        }
    }

    /**
     * @see https://github.com/composer/composer/blob/34c371f5f23e25eb9aa54ccc65136cf50930612e/bin/composer#L20-L50
     */
    private function bumpMemoryLimit(): void
    {
        $memoryLimit = trim(ini_get('memory_limit'));
        $memoryLimitInBytes = '-1' === $memoryLimit ? '-1' : memory_to_bytes($memoryLimit);

        $boxMemoryLimit = getenv(BOX_MEMORY_LIMIT);

        if (false === $boxMemoryLimit) {
            $boxMemoryLimitInBytes = false;
        } elseif ('-1' === $boxMemoryLimit) {
            $boxMemoryLimitInBytes = '-1';
        } else {
            $boxMemoryLimitInBytes = memory_to_bytes($boxMemoryLimit);
        }

        // Increase memory_limit if it is lower than 500MB
        if (false === $boxMemoryLimitInBytes && '-1' !== $memoryLimitInBytes && $memoryLimitInBytes < 1024 * 1024 * 512) {
            append_to_file($this->tmpIni, 'memory_limit=512M'.PHP_EOL);

            $this->logger->debug(
                sprintf(
                    'Bumped the memory limit from "%s" to "%s"',
                    $memoryLimit,
                    '512M'
                )
            );
        }

        // Set user defined memory limit
        if ($boxMemoryLimitInBytes && $memoryLimitInBytes !== $boxMemoryLimitInBytes) {
            append_to_file($this->tmpIni, 'memory_limit='.$boxMemoryLimitInBytes.PHP_EOL);

            $this->logger->debug(
                sprintf(
                    'Bumped the memory limit from "%s" to %s="%s"',
                    $memoryLimit,
                    BOX_MEMORY_LIMIT,
                    $boxMemoryLimit
                )
            );
        }
    }
}
