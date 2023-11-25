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

namespace KevinGH\Box\Phar;

use JetBrains\PhpStorm\ArrayShape;
use Phar;
use PharData;
use PharFileInfo;
use RecursiveDirectoryIterator;
use SplFileInfo;
use Symfony\Component\Filesystem\Path;
use UnexpectedValueException;
use function ksort;
use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\realpath;
use function sprintf;
use function var_export;
use const SORT_LOCALE_STRING;

/**
 * Represents the PHAR metadata (partially). The goal is to capture enough information to interpret a PHAR
 * without instantiating a Phar or PharData instance.
 *
 * @private
 */
final readonly class PharMeta
{
    /**
     * @param non-empty-string|null                                                          $stub
     * @param non-empty-string|null                                                          $version
     * @param non-empty-string|null                                                          $normalizedMetadata
     * @param non-empty-string|null                                                          $pubKeyContent
     * @param array<string, array{'compression': CompressionAlgorithm, compressedSize: int}> $filesMeta
     */
    public function __construct(
        public CompressionAlgorithm $compression,
        #[ArrayShape(['hash' => 'string', 'hash_type' => 'string'])]
        public ?array $signature,
        public ?string $stub,
        public ?string $version,
        public ?string $normalizedMetadata,
        public int $timestamp,
        public ?string $pubKeyContent,
        public array $filesMeta,
    ) {
    }

    public static function fromPhar(Phar|PharData $phar, ?string $pubKeyContent): self
    {
        $compression = $phar->isCompressed();
        $signature = $phar->getSignature();
        $stub = $phar->getStub();
        $version = $phar->getVersion();
        $metadata = $phar->getMetadata();
        $timestamp = $phar->getMTime();

        return new self(
            false === $compression ? CompressionAlgorithm::NONE : CompressionAlgorithm::from($compression),
            false === $signature ? null : $signature,
            '' === $stub ? null : $stub,
            '' === $version ? null : $version,
            // TODO: check $unserializeOptions here
            null === $metadata ? null : var_export($metadata, true),
            $timestamp,
            $pubKeyContent,
            self::collectFilesMeta($phar),
        );
    }

    public static function fromJson(string $json): self
    {
        $decodedJson = json_decode($json, true);

        $filesMeta = $decodedJson['filesMeta'];

        foreach ($filesMeta as &$fileMeta) {
            $fileMeta['compression'] = CompressionAlgorithm::from($fileMeta['compression']);
        }

        return new self(
            CompressionAlgorithm::from($decodedJson['compression']),
            $decodedJson['signature'],
            $decodedJson['stub'],
            $decodedJson['version'],
            $decodedJson['normalizedMetadata'],
            $decodedJson['timestamp'],
            $decodedJson['pubKeyContent'],
            $filesMeta,
        );
    }

    public function toJson(): string
    {
        return json_encode([
            'compression' => $this->compression,
            'signature' => $this->signature,
            'stub' => $this->stub,
            'version' => $this->version,
            'normalizedMetadata' => $this->normalizedMetadata,
            'timestamp' => $this->timestamp,
            'pubKeyContent' => $this->pubKeyContent,
            'filesMeta' => $this->filesMeta,
        ]);
    }

    /**
     * @return array<string, array{'compression': CompressionAlgorithm, compressedSize: int}>
     */
    private static function collectFilesMeta(Phar|PharData $phar): array
    {
        $filesMeta = [];

        $root = self::getPharRoot($phar);

        self::traverseSource(
            $root,
            $phar,
            $filesMeta,
        );

        ksort($filesMeta, SORT_LOCALE_STRING);

        return $filesMeta;
    }

    /**
     * @param iterable<string, SplFileInfo|PharFileInfo> $source
     *
     * @return array<string, array{'compression': CompressionAlgorithm, compressedSize: int}>
     */
    private static function traverseSource(
        string $root,
        iterable $source,
        array &$filesMeta,
    ): void {
        foreach ($source as $path => $pharFileInfo) {
            if (!($pharFileInfo instanceof PharFileInfo)) {
                $pharFileInfo = new PharFileInfo($path);
            }

            if ($pharFileInfo->isDir()) {
                self::traverseSource(
                    $root,
                    new RecursiveDirectoryIterator($pharFileInfo->getPathname()),
                    $filesMeta,
                );

                continue;
            }

            $relativePath = Path::makeRelative($path, $root);

            $filesMeta[$relativePath] = [
                'compression' => self::getCompressionAlgorithm($pharFileInfo),
                'compressedSize' => $pharFileInfo->getCompressedSize(),
            ];
        }
    }

    private static function getPharRoot(Phar|PharData $phar): string
    {
        return 'phar://'.Path::normalize(realpath($phar->getPath()));
    }

    private static function getCompressionAlgorithm(PharFileInfo $pharFileInfo): CompressionAlgorithm
    {
        if (false === $pharFileInfo->isCompressed()) {
            return CompressionAlgorithm::NONE;
        }

        foreach (CompressionAlgorithm::cases() as $compressionAlgorithm) {
            if (CompressionAlgorithm::NONE !== $compressionAlgorithm
                && $pharFileInfo->isCompressed($compressionAlgorithm->value)
            ) {
                return $compressionAlgorithm;
            }
        }

        throw new UnexpectedValueException(
            sprintf(
                'Unknown compression algorithm for the file "%s',
                $pharFileInfo->getPath(),
            ),
        );
    }
}
