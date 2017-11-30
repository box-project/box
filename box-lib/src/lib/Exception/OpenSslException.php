<?php

namespace Herrera\Box\Exception;

/**
 * Use for errors when using the OpenSSL extension.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class OpenSslException extends Exception
{
    /**
     * Creates an exception for the last OpenSSL error.
     *
     * @return OpenSslException The exception.
     */
    public static function lastError()
    {
        return new static(openssl_error_string());
    }

    /**
     * Clears the error buffer, preventing unassociated error messages from
     * being used by the lastError() method. This is required for lsatError()
     * to function properly. If the clearing loop continues beyond a certain
     * number, a warning will be triggered before the loop is broken.
     *
     * @param integer $count The maximum number of rounds.
     */
    public static function reset($count = 100)
    {
        $counter = 0;

        while (openssl_error_string()) {
            if ($count < ++$counter) {
                trigger_error(
                    "The OpenSSL error clearing loop has exceeded $count rounds.",
                    E_USER_WARNING
                );

                break;
            }
        }
    }
}
