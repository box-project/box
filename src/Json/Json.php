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

use InvalidArgumentException;
use JsonSchema\Validator;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;
use stdClass;

final class Json
{
    private $linter;
    private $validator;

    public function __construct()
    {
        $this->linter = new JsonParser();
        $this->validator = new Validator();
    }

    public function decode(string $json): stdClass
    {
        $data = json_decode($json);

        if (JSON_ERROR_NONE !== ($error = json_last_error())) {
            if (JSON_ERROR_UTF8 === $error) {
                throw JsonValidationException::createDecodeException($error);
            }

            $this->lint($json);
            if (($result = $this->linter->lint($json)) instanceof ParsingException) {
                throw $result;
            }
        }

        return $data;
    }

    public function decodeFile(string $file): stdClass
    {
        if (false === is_file($file)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The file "%s" does not exist.',
                    $file
                )
            );
        }

        if (false === ($json = @file_get_contents($file))) {
            throw new InvalidArgumentException(
                sprintf(
                    'Could not read the file "%s": %s',
                    $file,
                    error_get_last()['message']
                )
            );
        }

        return $this->decode($json);
    }

    public function lint(string $json): void
    {
        $result = $this->linter->lint($json);

        if ($result instanceof ParsingException) {
            throw $result;
        }
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

            foreach ((array) $validator->getErrors() as $error) {
                $errors[] = ($error['property'] ? $error['property'].' : ' : '').$error['message'];
            }

            throw new JsonValidationException(
                '"'.$file.'" does not match the expected JSON schema',
                $errors
            );
        }
    }
}
