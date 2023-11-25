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

use Fidry\FileSystem\FS;
use JsonSchema\Validator;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;
use stdClass;
use function implode;
use function json_decode;
use function json_last_error;
use const JSON_ERROR_NONE;
use const JSON_ERROR_UTF8;

/**
 * @private
 */
final readonly class Json
{
    private JsonParser $linter;

    public function __construct()
    {
        $this->linter = new JsonParser();
    }

    /**
     * @throws ParsingException
     */
    public function lint(string $json): void
    {
        $result = $this->linter->lint($json);

        if ($result instanceof ParsingException) {
            throw $result;
        }
    }

    /**
     * @throws ParsingException
     */
    public function decode(string $json, bool $assoc = false): array|stdClass
    {
        $data = json_decode($json, $assoc);

        if (JSON_ERROR_NONE !== ($error = json_last_error())) {
            // Swallow the UTF-8 error and relies on the lint instead otherwise
            if (JSON_ERROR_UTF8 === $error) {
                throw new ParsingException('JSON decoding failed: Malformed UTF-8 characters, possibly incorrectly encoded');
            }

            $this->lint($json);
        }

        return false === $assoc ? (object) $data : $data;   // If JSON is an empty JSON json_decode returns an empty
        // array instead of an stdClass instance
    }

    /**
     * @throws ParsingException
     */
    public function decodeFile(string $file, bool $assoc = false): array|stdClass
    {
        $json = FS::getFileContents($file);

        return $this->decode($json, $assoc);
    }

    /**
     * Validates the decoded JSON data.
     *
     * @param string   $file   The JSON file
     * @param stdClass $json   The decoded JSON data
     * @param stdClass $schema The JSON schema
     *
     * @throws JsonValidationException If the JSON data failed validation
     */
    public function validate(string $file, stdClass $json, stdClass $schema): void
    {
        $validator = new Validator();
        $validator->check($json, $schema);

        if (!$validator->isValid()) {
            $errors = [];

            foreach ($validator->getErrors() as $error) {
                $errors[] = ($error['property'] ? $error['property'].' : ' : '').$error['message'];
            }

            $message = [] !== $errors
                ? "\"{$file}\" does not match the expected JSON schema:\n  - ".implode("\n  - ", $errors)
                : "\"{$file}\" does not match the expected JSON schema.";

            throw new JsonValidationException($message, $file, $errors);
        }
    }
}
