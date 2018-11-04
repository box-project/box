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

namespace KevinGH\Box\Annotation\Exception;

use Exception;

/**
 * Provides additional functional to the Exception class.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Exception extends Exception implements ExceptionInterface
{
    /**
     * Creates a new exception using a format and values.
     *
     * @param string $format    the format
     * @param mixed  $value,... The value(s).
     *
     * @return Exception the exception
     */
    public static function create($format, $value = null): self
    {
        if (0 < func_num_args()) {
            $format = vsprintf($format, array_slice(func_get_args(), 1));
        }

        return new static($format);
    }

    /**
     * Creates an exception for the last error message.
     *
     * @return Exception the exception
     */
    public static function lastError(): self
    {
        $error = error_get_last();

        return new static($error['message']);
    }
}
