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

namespace KevinGH\Box\Composer;

use Composer\Semver\Semver;
use Fidry\Console\IO;
use Fidry\FileSystem\FileSystem;
use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use KevinGH\Box\Composer\Throwable\IncompatibleComposerVersion;
use KevinGH\Box\Composer\Throwable\UndetectableComposerVersion;
use KevinGH\Box\NotInstantiable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use function sprintf;
use function trim;
use const PHP_EOL;

/**
 * @private
 */
final class ComposerOrchestrator
{
    use NotInstantiable;

    public const SUPPORTED_VERSION_CONSTRAINTS = '^2.2.0';

    private string $detectedVersion;

    public static function create(): self
    {
        return new self(
            ComposerProcessFactory::create(io: IO::createNull()),
            new NullLogger(),
            new FileSystem(),
        );
    }

    public function __construct(
        private readonly ComposerProcessFactory $processFactory,
        private readonly LoggerInterface $logger,
        private readonly FileSystem $fileSystem,
    ) {
    }

    /**
     * @throws UndetectableComposerVersion
     */
    public function getVersion(): string
    {
        if (isset($this->detectedVersion)) {
            return $this->detectedVersion;
        }

        $getVersionProcess = $this->processFactory->getVersionProcess();

        $this->logger->info($getVersionProcess->getCommandLine());

        $getVersionProcess->run();

        if (false === $getVersionProcess->isSuccessful()) {
            throw UndetectableComposerVersion::forFailedProcess($getVersionProcess);
        }

        $output = $getVersionProcess->getOutput();

        if (1 !== preg_match('/Composer version (\S+?) /', $output, $match)) {
            throw UndetectableComposerVersion::forOutput(
                $getVersionProcess,
                $output,
            );
        }

        $this->detectedVersion = $match[1];

        return $this->detectedVersion;
    }

    /**
     * @throws UndetectableComposerVersion
     * @throws IncompatibleComposerVersion
     */
    public function checkVersion(): void
    {
        $version = $this->getVersion();

        $this->logger->info(
            sprintf(
                'Version detected: %s (Box requires %s)',
                $version,
                self::SUPPORTED_VERSION_CONSTRAINTS,
            ),
        );

        if (!Semver::satisfies($version, self::SUPPORTED_VERSION_CONSTRAINTS)) {
            throw IncompatibleComposerVersion::create($version, self::SUPPORTED_VERSION_CONSTRAINTS);
        }
    }

    /**
     * @param string[] $excludedComposerAutoloadFiles Relative paths of the files that were not scoped hence which need
     *                                                to be configured as loaded to Composer as otherwise they would be
     *                                                autoloaded twice.
     */
    public function dumpAutoload(
        SymbolsRegistry $symbolsRegistry,
        string $prefix,
        bool $excludeDevFiles,
        array $excludedComposerAutoloadFiles,
    ): void {
        $this->dumpAutoloader(true === $excludeDevFiles);

        if ('' === $prefix) {
            return;
        }

        $vendorDir = $this->getVendorDir();
        $autoloadFile = $vendorDir.'/autoload.php';

        $autoloadContents = AutoloadDumper::generateAutoloadStatements(
            $symbolsRegistry,
            $vendorDir,
            $excludedComposerAutoloadFiles,
            $this->fileSystem->getFileContents($autoloadFile),
        );

        $this->fileSystem->dumpFile($autoloadFile, $autoloadContents);
    }

    /**
     * @return string The vendor-dir directory path relative to its composer.json.
     */
    public function getVendorDir(): string
    {
        $vendorDirProcess = $this->processFactory->getVendorDirProcess();

        $this->logger->info($vendorDirProcess->getCommandLine());

        $vendorDirProcess->run();

        if (false === $vendorDirProcess->isSuccessful()) {
            throw new RuntimeException(
                'Could not retrieve the vendor dir.',
                0,
                new ProcessFailedException($vendorDirProcess),
            );
        }

        return trim($vendorDirProcess->getOutput());
    }

    private function dumpAutoloader(bool $noDev): void
    {
        $dumpAutoloadProcess = $this->processFactory->getDumpAutoloaderProcess($noDev);

        $this->logger->info($dumpAutoloadProcess->getCommandLine());

        $dumpAutoloadProcess->run();

        if (false === $dumpAutoloadProcess->isSuccessful()) {
            throw new RuntimeException(
                'Could not dump the autoloader.',
                0,
                new ProcessFailedException($dumpAutoloadProcess),
            );
        }

        $output = $dumpAutoloadProcess->getOutput();
        $errorOutput = $dumpAutoloadProcess->getErrorOutput();

        if ('' !== $output) {
            $this->logger->info(
                'STDOUT output:'.PHP_EOL.$output,
                ['stdout' => $output],
            );
        }

        if ('' !== $errorOutput) {
            $this->logger->info(
                'STDERR output:'.PHP_EOL.$errorOutput,
                ['stderr' => $errorOutput],
            );
        }
    }
}
