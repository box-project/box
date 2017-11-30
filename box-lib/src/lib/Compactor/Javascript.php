<?php

namespace Herrera\Box\Compactor;

use JShrink\Minifier;

/**
 * Compacts Javascript files using JShrink.
 *
 * @author Robert Hafner <tedivm@tedivm.com>
 */
class Javascript extends Compactor
{
    /**
     * The default list of supported file extensions.
     *
     * @var array
     */
    protected $extensions = array('js');

    /**
     * {@inheritDoc}
     */
    public function compact($contents)
    {
        try {
            return Minifier::minify($contents);
        } catch (\Exception $e) {

            return $contents;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports($file)
    {
        if (!parent::supports($file)) {
            return false;
        }

        return !(substr($file, -7) == '.min.js');
    }

}
