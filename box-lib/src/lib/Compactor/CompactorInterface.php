<?php

namespace Herrera\Box\Compactor;

/**
 * Defines how a source code compacting class must be implemented.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
interface CompactorInterface
{
    /**
     * Compacts the file contents.
     *
     * @param string $contents The contents.
     *
     * @return string The compacted file contents.
     */
    public function compact($contents);

    /**
     * Checks if the file is supported.
     *
     * @param string $file The file name.
     *
     * @return boolean TRUE if it is supported, FALSE if not.
     */
    public function supports($file);
}
