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

namespace KevinGH\Box\RequirementChecker;

/**
 * @private
 */
final class Requirement
{
    public function __construct(
        public readonly string $type,
        public readonly string $condition,
        public readonly string $message,
        public readonly string $helpMessage,
    ) {
    }

    public static function forPHP(string $requiredPhpVersion, ?string $packageName): self
    {
        return new self(
            'php',
            $requiredPhpVersion,
            null === $packageName
                ? sprintf(
                    'The application requires a version matching "%s".',
                    $requiredPhpVersion,
                )
                : sprintf(
                    'The package "%s" requires a version matching "%s".',
                    $packageName,
                    $requiredPhpVersion,
                ),
            null === $packageName
                ? sprintf(
                    'The application requires a version matching "%s".',
                    $requiredPhpVersion,
                )
                : sprintf(
                    'The package "%s" requires a version matching "%s".',
                    $packageName,
                    $requiredPhpVersion,
                ),
        );
    }

    public static function forExtension(string $extension, ?string $packageName): self
    {
        return new self(
            'extension',
            $extension,
            null === $packageName
                ? sprintf(
                    'The application requires the extension "%s". Enable it or install a polyfill.',
                    $extension,
                )
                : sprintf(
                    'The application requires the extension "%s".',
                    $extension,
                ),
            null === $packageName
                ? sprintf(
                    'The package "%s" requires the extension "%s". Enable it or install a polyfill.',
                    $packageName,
                    $extension,
                )
                : sprintf(
                    'The package "%s" requires the extension "%s".',
                    $packageName,
                    $extension,
                ),
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'condition' => $this->condition,
            'message' => $this->message,
            'helpMessage' => $this->helpMessage,
        ];
    }
}
