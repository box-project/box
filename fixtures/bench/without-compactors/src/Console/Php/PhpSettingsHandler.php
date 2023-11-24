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

namespace BenchTest\Console\Php;

use BenchTest\Constants;
use BenchTest\Phar\PharPhpSettings;
use Composer\XdebugHandler\XdebugHandler;
use Fidry\FileSystem\FS;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;
use function BenchTest\format_size;
use function BenchTest\memory_to_bytes;
use function getenv;
use function ini_get;
use function ini_set;
use function sprintf;
use function trim;
use const PHP_EOL;

/**
 * @private
 */
final class PhpSettingsHandler extends XdebugHandler
{
    private LoggerInterface $logger;
    private bool $pharReadonly;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct('box');

        $this->setPersistent();

        $this->setLogger($logger);
        $this->logger = $logger;

        $this->pharReadonly = PharPhpSettings::isReadonly();
        $this->setPersistent();
    }

    public function check(): void
    {
        $this->bumpMemoryLimit();

        parent::check();
    }

    protected function requiresRestart(bool $default): bool
    {
        if ($this->pharReadonly) {
            $this->logger->debug('phar.readonly is enabled');

            return true;
        }

        $this->logger->debug('phar.readonly is disabled');

        return parent::requiresRestart($default);
    }

    protected function restart(array $command): void
    {
        // Disable phar.readonly if set
        $this->disablePharReadonly();

        parent::restart($command);
    }

    private function disablePharReadonly(): void
    {
        if (PharPhpSettings::isReadonly()) {
            Assert::notNull($this->tmpIni);

            FS::appendToFile($this->tmpIni, 'phar.readonly=0'.PHP_EOL);

            $this->logger->debug('Configured `phar.readonly=0`');
        }
    }

    /**
     * @see https://github.com/composer/composer/blob/34c371f5f23e25eb9aa54ccc65136cf50930612e/bin/composer#L20-L50
     */
    private function bumpMemoryLimit(): void
    {
        $userDefinedMemoryLimit = self::getUserDefinedMemoryLimit();

        $memoryLimit = trim(ini_get('memory_limit'));
        $memoryLimitInBytes = '-1' === $memoryLimit ? -1 : memory_to_bytes($memoryLimit);

        // Whether the memory limit should be dumped
        $bumpMemoryLimit = (
            null === $userDefinedMemoryLimit
            && -1 !== $memoryLimitInBytes
            && $memoryLimitInBytes < 1024 * 1024 * 512
        );
        // Whether the memory limit should be set to the user defined memory limit
        $setUserDefinedMemoryLimit = (
            null !== $userDefinedMemoryLimit
            && $memoryLimitInBytes !== $userDefinedMemoryLimit
        );

        if ($bumpMemoryLimit && false === $setUserDefinedMemoryLimit) {
            ini_set('memory_limit', '512M');

            $this->logger->debug(
                sprintf(
                    'Changed the memory limit from "%s" to "%s"',
                    format_size($memoryLimitInBytes, 0),
                    '512M',
                ),
            );
        } elseif ($setUserDefinedMemoryLimit) {
            ini_set('memory_limit', (string) $userDefinedMemoryLimit);

            $this->logger->debug(
                sprintf(
                    'Changed the memory limit from "%s" to %s="%s"',
                    format_size($memoryLimitInBytes, 0),
                    Constants::MEMORY_LIMIT,
                    format_size($userDefinedMemoryLimit, 0),
                ),
            );
        } else {
            $this->logger->debug(
                sprintf(
                    'Current memory limit: "%s"',
                    format_size($memoryLimitInBytes, 0),
                ),
            );
        }
    }

    private static function getUserDefinedMemoryLimit(): ?int
    {
        $memoryLimit = getenv(Constants::MEMORY_LIMIT);

        if (false === $memoryLimit) {
            $memoryLimitInBytes = null;
        } elseif ('-1' === $memoryLimit) {
            $memoryLimitInBytes = -1;
        } else {
            $memoryLimitInBytes = memory_to_bytes($memoryLimit);
        }

        return $memoryLimitInBytes;
    }
}
