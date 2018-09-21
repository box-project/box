<?php

declare(strict_types=1);

namespace KevinGH\Box;


use function array_keys;
use Assert\Assertion;
use function trim;

final class ConfigurationLogger
{
    private $recommendations = [];
    private $warnings = [];

    public function addRecommendation(string $message): void
    {
        $message = trim($message);

        Assertion::false('' === $message, 'Expected to have a message but a blank string was given instead.');

        $this->recommendations[$message] = $message;
    }

    public function addWarning(string $message): void
    {
        $message = trim($message);

        Assertion::false('' === $message, 'Expected to have a message but a blank string was given instead.');

        $this->warnings[$message] = $message;
    }

    /**
     * @return string[]
     */
    public function getRecommendations(): array
    {
        return array_keys($this->recommendations);
    }

    /**
     * @return string[]
     */
    public function getWarnings(): array
    {
        return array_keys($this->warnings);
    }
}