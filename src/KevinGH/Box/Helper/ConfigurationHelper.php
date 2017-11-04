<?php

namespace KevinGH\Box\Helper;

use Herrera\Json\Json;
use KevinGH\Box\Configuration;
use Phine\Path\Path;
use RuntimeException;
use Symfony\Component\Console\Helper\Helper;

/**
 * The Box schema file path.
 *
 * @var string
 */
define('BOX_SCHEMA_FILE', BOX_PATH . '/res/schema.json');

/**
 * Manages the acquisition of configuration settings.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class ConfigurationHelper extends Helper
{
    /**
     * The name of the default configuration file.
     *
     * @var string
     */
    const FILE_NAME = 'box.json';

    /**
     * The JSON processor.
     *
     * @var Json
     */
    private $json;

    /**
     * Creates the JSON processor.
     */
    public function __construct()
    {
        $this->json = new Json();
    }

    /**
     * @override
     */
    public function getName()
    {
        return 'config';
    }

    /**
     * Returns the file path to the default configuration file.
     *
     * @return string The file path.
     *
     * @throws RuntimeException If the default file does not exist.
     */
    public function getDefaultPath()
    {
        if (false === file_exists(self::FILE_NAME)) {
            if (false === file_exists(self::FILE_NAME . '.dist')) {
                throw new RuntimeException(
                    sprintf('The configuration file could not be found.')
                );
            }

            return realpath(self::FILE_NAME . '.dist');
        }

        return realpath(self::FILE_NAME);
    }

    /**
     * Loads the configuration file and returns it.
     *
     * @param string $file The configuration file path.
     *
     * @return Configuration The configuration settings.
     */
    public function loadFile($file = null)
    {
        if (null === $file) {
            $file = $this->getDefaultPath();
        }

        $json = $this->json->decodeFile($file);

        if (isset($json->import)) {
            if (!Path::isAbsolute($json->import)) {
                $json->import = Path::join(
                    array(
                        dirname($file),
                        $json->import
                    )
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

        return new Configuration($file, $json);
    }
}
