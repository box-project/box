<?php

namespace Herrera\Box\Signature;

/**
 * Buffers the hash as opposed to updating incrementally.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
abstract class AbstractBufferedHash implements VerifyInterface
{
    /**
     * The buffered data.
     *
     * @var string
     */
    private $data;

    /**
     * @see VerifyInterface::update
     */
    public function update($data)
    {
        $this->data .= $data;
    }

    /**
     * Returns the buffered data.
     *
     * @return string The data.
     */
    protected function getData()
    {
        return $this->data;
    }
}
