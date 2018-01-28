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
use RuntimeException;
use Symfony\Component\Console\Helper\Helper;
use Webmozart\PathUtil\Path;
use function KevinGH\Box\is_absolute;

/*
 * The Box schema file path.
 *
 * @var string
 */
define('BOX_SCHEMA_FILE', BOX_PATH.'/res/schema.json');

final class ConfigurationHelper extends Helper
{
    private const FILE_NAME = 'box.json';

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
                throw new RuntimeException(
                    sprintf('The configuration file could not be found.')
                );
            }

            return realpath(self::FILE_NAME.'.dist');
        }

        return realpath(self::FILE_NAME);
    }

    public function loadFile(?string $file): Configuration
    {
        if (null === $file) {
            $file = $this->findDefaultPath();
        }

        $json = $this->json->decodeFile($file);

        // Include imports
        if (isset($json->import)) {
            if (!is_absolute($json->import)) {
                $json->import = Path::join(
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
            $this->json->decodeFile(BOX_SCHEMA_FILE)
        );

        return Configuration::create($file, $json);
    }
}
