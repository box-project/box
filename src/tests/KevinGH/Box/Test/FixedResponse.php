<?php

namespace KevinGH\Box\Test;

use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Returns a fixed response for the dialog request.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class FixedResponse extends QuestionHelper
{
    /**
     * The fixed response.
     *
     * @var mixed
     */
    private $response;

    /**
     * Sets the fixed response.
     *
     * @param string $response The fixed response.
     */
    public function __construct($response)
    {
        $this->response = $response;
    }

    /**
     * @override
     */
    public function askHiddenResponse(
        OutputInterface $output,
        $question,
        $fallback = true
    ) {
        return $this->response;
    }
}
