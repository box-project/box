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

use Exception;
use UnexpectedValueException;

final class JsonValidationException extends UnexpectedValueException
{
    private $errors;

    /**
     * @inheritdoc
     */
    public function __construct(string $message, $errors = [], Exception $previous = null)
    {
        $this->errors = $errors;

        parent::__construct($message, 0, $previous);
    }

    /**
     * Creates an exception according to a given code with a customized message.
     *
     * @param int $code return code of json_last_error function
     *
     * @return static
     */
    public static function createDecodeException(int $code): self
    {
        switch ($code) {
            case JSON_ERROR_CTRL_CHAR:
                $msg = 'Control character error, possibly incorrectly encoded.';
                break;
            case JSON_ERROR_DEPTH:
                $msg = 'The maximum stack depth has been exceeded.';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $msg = 'Invalid or malformed JSON.';
                break;
            case JSON_ERROR_SYNTAX:
                $msg = 'Syntax error.';
                break;
            case JSON_ERROR_UTF8:
                $msg = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
                break;
            default:
                $msg = 'Unknown error';
        }

        return new self('JSON decoding failed: '.$msg);
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
