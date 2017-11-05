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

namespace KevinGH\Box\Test;

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
     * @param string $response the fixed response
     */
    public function __construct($response)
    {
        $this->response = $response;
    }

    /**
     * @override
     *
     * @param mixed $question
     * @param mixed $fallback
     */
    public function askHiddenResponse(
        OutputInterface $output,
        $question,
        $fallback = true
    ) {
        return $this->response;
    }
}
