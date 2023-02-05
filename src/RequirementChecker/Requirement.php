<?php

declare(strict_types=1);

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
