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

namespace KevinGH\Box\Console\Php;

use Composer\XdebugHandler\Process;
use Composer\XdebugHandler\XdebugHandler;
use function getenv;
use function ini_get;
use function ini_set;
use const KevinGH\Box\BOX_MEMORY_LIMIT;
use function KevinGH\Box\FileSystem\append_to_file;
use function KevinGH\Box\format_size;
use function KevinGH\Box\memory_to_bytes;
use const PHP_EOL;
use Psr\Log\LoggerInterface;
use function sprintf;
use function trim;

/**
 * @private
 */
final class PhpSettingsHandler extends XdebugHandler
{
    private $logger;
    private $pharReadonly;
    private $boxMemoryLimitInBytes;

    /**
     * {@inheritdoc}
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct('box', '--ansi');

        $this->setLogger($logger);
        $this->logger = $logger;

        $this->pharReadonly = '1' === ini_get('phar.readonly');
        $this->boxMemoryLimitInBytes = $this->checkMemoryLimit();
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

        // Bump the memory limit in the current process if necessary
        $this->bumpMemoryLimit();
    }

    /**
     * {@inheritdoc}
     */
    protected function requiresRestart($isLoaded): bool
    {
        if ($this->pharReadonly) {
            $this->logger->debug('phar.readonly is enabled');

            return true;
        }

        $this->logger->debug('phar.readonly is disabled');

        return parent::requiresRestart($isLoaded);
    }

    /**
     * {@inheritdoc}
     */
    protected function restart($command): void
    {
        // Disable phar.readonly if set
        $this->disablePharReadonly();

        // Bump the memory limit in the restated process if necessary
        $this->bumpMemoryLimit();

        parent::restart($command);
    }

    /**
     * @return false|int the desired memory limit for Box
     */
    private function checkMemoryLimit()
    {
        $memoryLimit = getenv(BOX_MEMORY_LIMIT);

        if (false === $memoryLimit) {
            $memoryLimitInBytes = false;
        } elseif ('-1' === $memoryLimit) {
            $memoryLimitInBytes = -1;
        } else {
            $memoryLimitInBytes = memory_to_bytes($memoryLimit);
        }

        return $memoryLimitInBytes;
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
        $memoryLimitInBytes = '-1' === $memoryLimit ? -1 : memory_to_bytes($memoryLimit);

        $bumpMemoryLimit = false === $this->boxMemoryLimitInBytes && -1 !== $memoryLimitInBytes && $memoryLimitInBytes < 1024 * 1024 * 512;
        $setUserDefinedMemoryLimit = $this->boxMemoryLimitInBytes && $memoryLimitInBytes !== $this->boxMemoryLimitInBytes;

        if ($bumpMemoryLimit && false === $setUserDefinedMemoryLimit) {
            if ($this->tmpIni) {
                // Is for the restarted process
                append_to_file($this->tmpIni, 'memory_limit=512M'.PHP_EOL);
            } else {
                // Is for the current process
                ini_set('memory_limit', '512M');
            }

            $this->logger->debug(
                sprintf(
                    'Changed the memory limit from "%s" to "%s"',
                    format_size($memoryLimitInBytes, 0),
                    '512M'
                )
            );
        } elseif ($setUserDefinedMemoryLimit) {
            if ($this->tmpIni) {
                // Is for the restarted process
                append_to_file($this->tmpIni, 'memory_limit='.$this->boxMemoryLimitInBytes.PHP_EOL);
            } else {
                // Is for the current process
                ini_set('memory_limit', (string) $this->boxMemoryLimitInBytes);
            }

            $this->logger->debug(
                sprintf(
                    'Changed the memory limit from "%s" to %s="%s"',
                    format_size($memoryLimitInBytes, 0),
                    BOX_MEMORY_LIMIT,
                    format_size($this->boxMemoryLimitInBytes, 0)
                )
            );
        } else {
            $this->logger->debug(
                sprintf(
                    'Current memory limit: "%s"',
                    format_size($memoryLimitInBytes, 0)
                )
            );
        }
    }
}
