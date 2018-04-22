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

use Composer\XdebugHandler\XdebugHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use const FILE_APPEND;
use const PHP_EOL;
use function file_put_contents;

/**
 * @private
 */
final class PhpSettingsHandler extends XdebugHandler
{
    private $logger;
    private $required;

    /**
     * {@inheritdoc}
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct('box', '--ansi');

        $this->setLogger($logger);
        $this->logger = $logger;

        $this->required = (bool) ini_get('phar.readonly');
    }

    /**
     * {@inheritdoc}
     */
    protected function requiresRestart($isLoaded)
    {
        return $this->required || $isLoaded;
    }

    /**
     * {@inheritdoc}
     */
    protected function restart($command): void
    {
        if ($this->required) {
            if (false === @file_put_contents($this->tmpIni, 'phar.readonly=0'.PHP_EOL, FILE_APPEND)) {
                throw new IOException(
                    sprintf('Failed to write file "%s".', $this->tmpIni),
                    0,
                    null,
                    $this->tmpIni
                );
            }

            $this->logger->debug('Configured `phar.readonly=0`');
        }

        parent::restart($command);
    }
}
