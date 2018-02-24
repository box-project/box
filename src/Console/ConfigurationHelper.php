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

namespace KevinGH\Box\Console;

use KevinGH\Box\Configuration;
use KevinGH\Box\Json\Json;
use KevinGH\Box\Throwable\Exception\NoConfigurationFound;
use stdClass;
use Symfony\Component\Console\Helper\Helper;
use function KevinGH\Box\FileSystem\is_absolute_path;

final class ConfigurationHelper extends Helper
{
    private const FILE_NAME = 'box.json';
    private const SCHEMA_FILE = __DIR__.'/../../res/schema.json';

    private $json;

    public function __construct()
    {
        $this->json = new Json();
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'config';
    }

    public function findDefaultPath(): string
    {
        if (false === file_exists(self::FILE_NAME)) {
            if (false === file_exists(self::FILE_NAME.'.dist')) {
                throw new NoConfigurationFound();
            }

            return realpath(self::FILE_NAME.'.dist');
        }

        return realpath(self::FILE_NAME);
    }

    public function loadFile(?string $file): Configuration
    {
        if (null === $file) {
            return Configuration::create(null, new stdClass());
        }

        $json = $this->json->decodeFile($file);

        // Include imports
        if (isset($json->import)) {
            if (!is_absolute_path($json->import)) {
                $json->import = implode(
                    [
                        dirname($file),
                        $json->import,
                    ]
                );
            }

            $json = (object) array_merge(
                (array) $this->json->decodeFile($json->import),
                (array) $json
            );
        }

        $this->json->validate(
            $file,
            $json,
            $this->json->decodeFile(self::SCHEMA_FILE)
        );

        return Configuration::create($file, $json);
    }
}
