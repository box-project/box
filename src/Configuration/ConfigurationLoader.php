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

namespace KevinGH\Box\Configuration;

use KevinGH\Box\Json\Json;
use stdClass;

/**
 * @private
 */
final readonly class ConfigurationLoader
{
    private const SCHEMA_FILE = __DIR__.'/../../res/schema.json';

    public function __construct(private Json $json = new Json())
    {
    }

    /**
     * @param null|non-empty-string $file
     */
    public function loadFile(?string $file): Configuration
    {
        if (null === $file) {
            return Configuration::create(null, new stdClass());
        }

        $json = $this->json->decodeFile($file);

        $this->json->validate(
            $file,
            $json,
            $this->json->decodeFile(self::SCHEMA_FILE),
        );

        return Configuration::create($file, $json);
    }
}
