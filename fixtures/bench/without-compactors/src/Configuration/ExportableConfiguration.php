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

namespace BenchTest\Configuration;

use BenchTest\Compactor\Compactors;
use BenchTest\Composer\ComposerFile;
use BenchTest\MapFile;
use Closure;
use SplFileInfo;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\SplFileInfo as SymfonySplFileInfo;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use function array_map;
use function iter\values;
use function sort;
use const SORT_STRING;

/**
 * A class similar to {@see Configuration} but for which the property types and values might change in order to improve
 * its readability when dumping it into a file.
 *
 * @internal
 */
final class ExportableConfiguration
{
    public static function create(Configuration $configuration): self
    {
        $normalizePath = self::createPathNormalizer($configuration->getBasePath());
        $normalizePaths = static function (array $files) use ($normalizePath): array {
            $files = array_map($normalizePath, $files);
            sort($files, SORT_STRING);

            return $files;
        };

        $composerJson = $configuration->getComposerJson();
        $composerLock = $configuration->getComposerLock();

        return new self(
            $normalizePath($configuration->getConfigurationFile()),
            $configuration->getAlias(),
            $configuration->getBasePath(),
            new ComposerFile(
                $normalizePath($composerJson),
                $configuration->getDecodedComposerJsonContents() ?? [],
            ),
            new ComposerFile(
                $normalizePath($composerLock),
                $configuration->getDecodedComposerLockContents() ?? [],
            ),
            $normalizePaths($configuration->getFiles()),
            $normalizePaths($configuration->getBinaryFiles()),
            $configuration->hasAutodiscoveredFiles(),
            $configuration->dumpAutoload(),
            $configuration->excludeComposerFiles(),
            $configuration->excludeDevFiles(),
            array_map('get_class', $configuration->getCompactors()->toArray()),
            $configuration->getCompressionAlgorithm()->name,
            '0'.decoct($configuration->getFileMode()),
            $normalizePath($configuration->getMainScriptPath()),
            $configuration->getMainScriptContents(),
            $configuration->getFileMapper(),
            $configuration->getMetadata(),
            $normalizePath($configuration->getTmpOutputPath()),
            $normalizePath($configuration->getOutputPath()),
            // TODO: remove this from the dump & add the SensitiveParam annotation
            $configuration->getPrivateKeyPassphrase(),
            $normalizePath($configuration->getPrivateKeyPath()),
            $configuration->promptForPrivateKey(),
            $configuration->getReplacements(),
            $configuration->getShebang(),
            $configuration->getSigningAlgorithm()->name,
            $configuration->getStubBannerContents(),
            $normalizePath($configuration->getStubBannerPath()),
            $normalizePath($configuration->getStubPath()),
            $configuration->isInterceptFileFuncs(),
            $configuration->isStubGenerated(),
            $configuration->checkRequirements(),
            $configuration->getWarnings(),
            $configuration->getRecommendations(),
        );
    }

    /**
     * @return Closure(null|SplFileInfo|string): string|null
     */
    private static function createPathNormalizer(string $basePath): Closure
    {
        return static function (null|SplFileInfo|string $path) use ($basePath): ?string {
            if (null === $path) {
                return null;
            }

            if ($path instanceof SplFileInfo) {
                $path = $path->getPathname();
            }

            return Path::makeRelative($path, $basePath);
        };
    }

    /** @noinspection PhpPropertyOnlyWrittenInspection */
    private function __construct(
        private readonly ?string $file,
        private readonly string $alias,
        private readonly string $basePath,
        private readonly ComposerFile $composerJson,
        private readonly ComposerFile $composerLock,
        private readonly array $files,
        private readonly array $binaryFiles,
        private readonly bool $autodiscoveredFiles,
        private readonly bool $dumpAutoload,
        private readonly bool $excludeComposerFiles,
        private readonly bool $excludeDevFiles,
        private readonly Compactors|array $compactors,
        private readonly string $compressionAlgorithm,
        private readonly int|string|null $fileMode,
        private readonly ?string $mainScriptPath,
        private readonly ?string $mainScriptContents,
        private readonly MapFile $fileMapper,
        private readonly mixed $metadata,
        private readonly string $tmpOutputPath,
        private readonly string $outputPath,
        private readonly ?string $privateKeyPassphrase,
        private readonly ?string $privateKeyPath,
        private readonly bool $promptForPrivateKey,
        private readonly array $processedReplacements,
        private readonly ?string $shebang,
        private readonly string $signingAlgorithm,
        private readonly ?string $stubBannerContents,
        private readonly ?string $stubBannerPath,
        private readonly ?string $stubPath,
        private readonly bool $isInterceptFileFuncs,
        private readonly bool $isStubGenerated,
        private readonly bool $checkRequirements,
        private readonly array $warnings,
        private readonly array $recommendations,
    ) {
    }

    public function export(): string
    {
        $cloner = new VarCloner();
        $cloner->setMaxItems(-1);
        $cloner->setMaxString(-1);

        $normalizePath = self::createPathNormalizer($this->basePath);
        $splInfoCaster = static fn (SplFileInfo $fileInfo): array => [$normalizePath($fileInfo)];

        $cloner->addCasters([
            SplFileInfo::class => $splInfoCaster,
            SymfonySplFileInfo::class => $splInfoCaster,
        ]);

        return (new CliDumper())->dump(
            $cloner->cloneVar($this),
            true,
        );
    }
}
