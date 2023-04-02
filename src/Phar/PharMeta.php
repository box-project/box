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
use function Safe\json_decode;
use function Safe\json_encode;
use function var_export;

/**
 * Represents the PHAR metadata (partially). The goal is to capture enough information to interpret a PHAR
 * without instantiating a Phar or PharData instance.
 *
 * @private
 */
final class PharMeta
{
    /**
     * @param non-empty-string|null $pubKeyContent
     */
    public function __construct(
        #[ArrayShape(['hash' => 'string', 'hash_type' => 'string'])]
        public readonly false|array $signature,
        public readonly string $stub,
        public readonly string $version,
        public readonly string $normalizedMetadata,
        public readonly ?string $pubKeyContent,
    ) {
    }

    public static function fromPhar(Phar|PharData $phar, ?string $pubKeyContent): self
    {
        return new self(
            $phar->getSignature(),
            $phar->getStub(),
            $phar->getVersion(),
            // TODO: check $unserializeOptions here
            var_export($phar->getMetadata(), true),
            $pubKeyContent,
        );
    }

    public static function fromJson(string $json): self
    {
        $decodedJson = json_decode($json, true);

        return new self(
            $decodedJson['signature'],
            $decodedJson['stub'],
            $decodedJson['version'],
            $decodedJson['normalizedMetadata'],
            $decodedJson['pubKeyContent'],
        );
    }

    public function toJson(): string
    {
        return json_encode([
            'signature' => $this->signature,
            'stub' => $this->stub,
            'version' => $this->version,
            'normalizedMetadata' => $this->normalizedMetadata,
            'pubKeyContent' => $this->pubKeyContent,
        ]);
    }
}
