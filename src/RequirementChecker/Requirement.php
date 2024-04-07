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
final readonly class Requirement
{
    public function __construct(
        public RequirementType $type,
        public string $condition,
        public ?string $source,
        public string $message,
        public string $helpMessage,
    ) {
    }

    public static function forPHP(string $requiredPhpVersion, ?string $packageName): self
    {
        return new self(
            RequirementType::PHP,
            $requiredPhpVersion,
            $packageName,
            null === $packageName
                ? sprintf(
                    'This application requires a PHP version matching "%s".',
                    $requiredPhpVersion,
                )
                : sprintf(
                    'The package "%s" requires a PHP version matching "%s".',
                    $packageName,
                    $requiredPhpVersion,
                ),
            null === $packageName
                ? sprintf(
                    'This application requires a PHP version matching "%s".',
                    $requiredPhpVersion,
                )
                : sprintf(
                    'The package "%s" requires a PHP version matching "%s".',
                    $packageName,
                    $requiredPhpVersion,
                ),
        );
    }

    public static function forRequiredExtension(string $extension, ?string $packageName): self
    {
        return new self(
            RequirementType::EXTENSION,
            $extension,
            $packageName,
            null === $packageName
                ? sprintf(
                    'This application requires the extension "%s".',
                    $extension,
                )
                : sprintf(
                    'The package "%s" requires the extension "%s".',
                    $packageName,
                    $extension,
                ),
            null === $packageName
                ? sprintf(
                    'This application requires the extension "%s". You either need to enable it or request the application to be shipped with a polyfill for this extension.',
                    $extension,
                )
                : sprintf(
                    'The package "%s" requires the extension "%s". You either need to enable it or request the application to be shipped with a polyfill for this extension.',
                    $packageName,
                    $extension,
                ),
        );
    }

    public static function forProvidedExtension(string $extension, ?string $packageName): self
    {
        return new self(
            RequirementType::PROVIDED_EXTENSION,
            $extension,
            $packageName,
            null === $packageName
                ? sprintf(
                    'This application provides the extension "%s".',
                    $extension,
                )
                : sprintf(
                    'The package "%s" provides the extension "%s".',
                    $packageName,
                    $extension,
                ),
            null === $packageName
                ? sprintf(
                    'This application does not require the extension "%s", it is provided by the application itself.',
                    $extension,
                )
                : sprintf(
                    'This application does not require the extension "%s", it is provided by the package "%s".',
                    $packageName,
                    $extension,
                ),
        );
    }

    public static function forConflictingExtension(string $extension, ?string $packageName): self
    {
        return new self(
            RequirementType::EXTENSION_CONFLICT,
            $extension,
            $packageName,
            null === $packageName
                ? sprintf(
                    'This application conflicts with the extension "%s".',
                    $extension,
                )
                : sprintf(
                    'The package "%s" conflicts with the extension "%s".',
                    $packageName,
                    $extension,
                ),
            null === $packageName
                ? sprintf(
                    'This application conflicts with the extension "%s". You need to disable it in order to run this application.',
                    $extension,
                )
                : sprintf(
                    'The package "%s" conflicts with the extension "%s". You need to disable it in order to run this application.',
                    $packageName,
                    $extension,
                ),
        );
    }

    public static function fromArray(array $value): self
    {
        return new self(
            RequirementType::from($value['type']),
            $value['condition'],
            $value['source'] ?? null,
            $value['message'],
            $value['helpMessage'],
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'condition' => $this->condition,
            'source' => $this->source,
            'message' => $this->message,
            'helpMessage' => $this->helpMessage,
        ];
    }
}
