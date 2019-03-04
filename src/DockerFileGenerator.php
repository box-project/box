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

namespace KevinGH\Box;

use function array_column;
use function array_filter;
use Assert\Assertion;
use function basename;
use Composer\Semver\Semver;
use function implode;
use function sprintf;
use function str_replace;
use UnexpectedValueException;

/**
 * @private
 */
final class DockerFileGenerator
{
    private const FILE_TEMPLATE = <<<'Dockerfile'
FROM php:__BASE_PHP_IMAGE_TOKEN__

RUN $(php -r '$extensionInstalled = array_map("strtolower", \get_loaded_extensions(false));$requiredExtensions = __PHP_EXTENSIONS_TOKEN__;$extensionsToInstall = array_diff($requiredExtensions, $extensionInstalled);if ([] !== $extensionsToInstall) {echo \sprintf("docker-php-ext-install %s", implode(" ", $extensionsToInstall));}echo "echo \"No extensions\"";')

COPY __PHAR_FILE_PATH_TOKEN__ /__PHAR_FILE_NAME_TOKEN__

ENTRYPOINT ["/__PHAR_FILE_NAME_TOKEN__"]

Dockerfile;

    private const PHP_DOCKER_IMAGES = [
        '7.3.0' => '7.3-cli-alpine',
        '7.2.0' => '7.2-cli-alpine',
        '7.1.0' => '7.1-cli-alpine',
        '7.0.0' => '7-cli-alpine',
    ];

    private $image;
    private $extensions;
    private $sourcePhar;

    /**
     * Creates a new instance of the generator.
     *
     * @param array  $requirements List of requirements following the format defined by the RequirementChecker component
     * @param string $sourcePhar   source PHAR location; This PHAR is going to be copied over to the image so the path
     *                             should either be absolute or relative to the location of the Dockerfile
     */
    public static function createForRequirements(array $requirements, string $sourcePhar): self
    {
        return new static(
            self::retrievePhpImageName($requirements),
            self::retrievePhpExtensions($requirements),
            $sourcePhar
        );
    }

    /**
     * @param string[] $extensions
     * @param string   $sourcePhar source PHAR location; This PHAR is going to be copied over to the image so the path
     *                             should either be absolute or relative to the location of the Dockerfile
     */
    public function __construct(string $image, array $extensions, string $sourcePhar)
    {
        Assertion::inArray($image, self::PHP_DOCKER_IMAGES);
        Assertion::allString($extensions);

        $this->image = $image;
        $this->extensions = $extensions;
        $this->sourcePhar = $sourcePhar;
    }

    /**
     * @return string The stub
     */
    public function generate(): string
    {
        $contents = self::FILE_TEMPLATE;

        $contents = str_replace(
            '__BASE_PHP_IMAGE_TOKEN__',
            $this->image,
            $contents
        );

        $contents = str_replace(
            '__PHP_EXTENSIONS_TOKEN__',
            [] === $this->extensions
                ? '[]'
                : sprintf(
                    '["%s"]',
                    implode(
                        '", "',
                        $this->extensions
                    )
                ),
            $contents
        );

        $contents = str_replace(
            '__PHAR_FILE_PATH_TOKEN__',
            $this->sourcePhar,
            $contents
        );

        $contents = str_replace(
            '__PHAR_FILE_NAME_TOKEN__',
            basename($this->sourcePhar),
            $contents
        );

        return $contents;
    }

    private static function retrievePhpImageName(array $requirements): string
    {
        $conditions = array_column(
            array_filter(
                $requirements,
                static function (array $requirement): bool {
                    return 'php' === $requirement['type'];
                }
            ),
            'condition'
        );

        foreach (self::PHP_DOCKER_IMAGES as $php => $image) {
            foreach ($conditions as $condition) {
                if (false === Semver::satisfies($php, $condition)) {
                    continue 2;
                }
            }

            return $image;
        }

        throw new UnexpectedValueException(
            sprintf(
                'Could not find a suitable Docker base image for the PHP constraint(s) "%s". Images available: "%s"',
                implode('", "', $conditions),
                implode('", "', self::PHP_DOCKER_IMAGES)
            )
        );
    }

    /**
     * @return string[]
     */
    private static function retrievePhpExtensions(array $requirements): array
    {
        return array_column(
            array_filter(
                $requirements,
                static function (array $requirement): bool {
                    return 'extension' === $requirement['type'];
                }
            ),
            'condition'
        );
    }
}
