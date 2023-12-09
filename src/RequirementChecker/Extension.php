<?php

declare(strict_types=1);

namespace KevinGH\Box\RequirementChecker;

// TODO: @internal
// TOOD: move under Composer
use function str_starts_with;
use function substr;

final readonly class Extension
{
    // Some extensions name differs in how they are registered in composer.json
    // and the name used when doing a `extension_loaded()` check.
    // See https://github.com/box-project/box/issues/653.
    private const EXTENSION_NAME_MAP = [
        'zend-opcache' => 'zend opcache',
    ];

    private const EXTENSION_REGEX = '/^ext-(?<extension>.+)$/';

    private const WILDCARD_CONSTRAINT = '*';

    public static function parse(string $package, string $constraint): self
    {
        return new self(
            // ext-ctype -> ctype
            substr($package, 4),
            // * -> null
            // ^1.1.2 -> unchanged
            $constraint === self::WILDCARD_CONSTRAINT   ? null : $constraint,
        );
    }

    public static function isExtension(string $name): bool
    {
        // TODO: remove the regex, this str_starts_with is easier
        return str_starts_with($name, 'ext-');
    }

    public function __construct(
        public string $name,
        public ?string $constraint = null,
    ) {
    }
}