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

namespace KevinGH\Box\Json;

use Throwable;
use UnexpectedValueException;
use Webmozart\Assert\Assert;

/**
 * @private
 */
final class JsonValidationException extends UnexpectedValueException
{
    private readonly ?string $validatedFile;
    private readonly array $errors;

    /**
     * @param string[] $errors
     */
    public function __construct(
        string $message,
        ?string $file = null,
        array $errors = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        if (null !== $file) {
            Assert::file($file);
        }
        Assert::allString($errors);

        $this->validatedFile = $file;
        $this->errors = $errors;

        parent::__construct($message, $code, $previous);
    }

    public function getValidatedFile(): ?string
    {
        return $this->validatedFile;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
