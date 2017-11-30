<?php

namespace Herrera\Box\Signature;

use Herrera\Box\Exception\Exception;
use Herrera\Box\Exception\SignatureException;

/**
 * Defines how a signature verification class must be implemented.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
interface VerifyInterface
{
    /**
     * Initializes the hash.
     *
     * @param string $algorithm The algorithm to use.
     * @param string $path      The path to the phar.
     *
     * @throws Exception
     * @throws SignatureException If the hash could not be initialized.
     */
    public function init($algorithm, $path);

    /**
     * Updates the hash with more data.
     *
     * @param string $data The data.
     *
     * @throws Exception
     * @throws SignatureException If the hash could not be updated.
     */
    public function update($data);

    /**
     * Verifies the final hash against the given signature.
     *
     * @param string $signature The signature.
     *
     * @return boolean TRUE if verified, FALSE if not.
     *
     * @throws Exception
     * @throws SignatureException If the hash could not be verified.
     */
    public function verify($signature);
}
