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

namespace KevinGH\Box\Configuration;

use Closure;
use KevinGH\Box\Compactor\Compactors;
use KevinGH\Box\Composer\Artifact\ComposerArtifact;
use KevinGH\Box\MapFile;
use SplFileInfo;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\SplFileInfo as SymfonySplFileInfo;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use function array_map;
use function sort;
use const SORT_STRING;

/**
 * A class similar to {@see Configuration} but for which the property types and values might change in order to improve
 * its readability when dumping it into a file.
 *
 * @internal
 */
final readonly class ExportableConfiguration
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
            null === $composerJson
                ? null
                : new ComposerArtifact(
                    $normalizePath($composerJson->path),
                    $composerJson->decodedContents,
                ),
            null === $composerLock
                ? null
                : new ComposerArtifact(
                    $normalizePath($composerLock->path),
                    $composerLock->decodedContents,
                ),
            $normalizePaths($configuration->getFiles()),
            $normalizePaths($configuration->getBinaryFiles()),
            $configuration->hasAutodiscoveredFiles(),
            $configuration->dumpAutoload(),
            $configuration->excludeComposerArtifacts(),
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
        private ?string $file,
        private string $alias,
        private string $basePath,
        private ?ComposerArtifact $composerJson,
        private ?ComposerArtifact $composerLock,
        private array $files,
        private array $binaryFiles,
        private bool $autodiscoveredFiles,
        private bool $dumpAutoload,
        private bool $excludeComposerArtifacts,
        private bool $excludeDevFiles,
        private array|Compactors $compactors,
        private string $compressionAlgorithm,
        private null|int|string $fileMode,
        private ?string $mainScriptPath,
        private ?string $mainScriptContents,
        private MapFile $fileMapper,
        private mixed $metadata,
        private string $tmpOutputPath,
        private string $outputPath,
        private ?string $privateKeyPassphrase,
        private ?string $privateKeyPath,
        private bool $promptForPrivateKey,
        private array $processedReplacements,
        private ?string $shebang,
        private string $signingAlgorithm,
        private ?string $stubBannerContents,
        private ?string $stubBannerPath,
        private ?string $stubPath,
        private bool $isInterceptFileFuncs,
        private bool $isStubGenerated,
        private bool $checkRequirements,
        private array $warnings,
        private array $recommendations,
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
