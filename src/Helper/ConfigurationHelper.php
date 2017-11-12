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

namespace KevinGH\Box\Helper;

use Herrera\Json\Json;
use KevinGH\Box\Configuration;
use Phine\Path\Path;
use RuntimeException;
use Symfony\Component\Console\Helper\Helper;

/*
 * The Box schema file path.
 *
 * @var string
 */
define('BOX_SCHEMA_FILE', BOX_PATH.'/res/schema.json');

final class ConfigurationHelper extends Helper
{
    /**
     * The name of the default configuration file.
     *
     * @var string
     */
    const FILE_NAME = 'box.json';

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

        if (isset($json->import)) {
            if (!Path::isAbsolute($json->import)) {
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
            $this->json->decodeFile(BOX_SCHEMA_FILE),
            $json
        );

        return Configuration::create($file, $json);
    }
}
