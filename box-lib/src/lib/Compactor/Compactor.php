<?php

namespace Herrera\Box\Compactor;

/**
 * An abstract compactor class that handles matching supported file types.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
abstract class Compactor implements CompactorInterface
{
    /**
     * The list of supported file extensions.
     *
     * @var array
     */
    protected $extensions;

    /**
     * Sets the list of supported file extensions.
     *
     * @param array $extensions The list.
     */
    public function setExtensions(array $extensions)
    {
        $this->extensions = $extensions;
    }

    /**
     * {@inheritDoc}
     */
    public function supports($file)
    {
        return in_array(pathinfo($file, PATHINFO_EXTENSION), $this->extensions);
    }
}
