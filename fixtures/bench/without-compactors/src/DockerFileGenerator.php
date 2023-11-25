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

namespace BenchTest;

use Composer\Semver\Semver;
use UnexpectedValueException;
use Webmozart\Assert\Assert;
use function array_column;
use function array_filter;
use function array_unique;
use function basename;
use function count;
use function implode;
use function sprintf;
use function strtr;
use const PHP_EOL;

/**
 * @private
 */
final class DockerFileGenerator
{
    private const FILE_TEMPLATE = <<<'Dockerfile'
        FROM php:__BASE_PHP_IMAGE_TOKEN__

        COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
        __REQUIRED_EXTENSIONS__
        COPY __PHAR_FILE_PATH_TOKEN__ /__PHAR_FILE_NAME_TOKEN__

        ENTRYPOINT ["/__PHAR_FILE_NAME_TOKEN__"]

        Dockerfile;

    private const PHP_DOCKER_IMAGES = [
        // TODO: allow future images
        '8.2.0' => '8.2-cli-alpine',
        '8.1.0' => '8.1-cli-alpine',
        '8.0.0' => '8.0-cli-alpine',
        '7.4.0' => '7.4-cli-alpine',
        '7.3.0' => '7.3-cli-alpine',
        '7.2.0' => '7.2-cli-alpine',
        '7.1.0' => '7.1-cli-alpine',
        '7.0.0' => '7-cli-alpine',
    ];

    private string $image;

    /**
     * @var string[]
     */
    private array $extensions;

    /**
     * Creates a new instance of the generator.
     *
     * @param array  $requirements List of requirements following the format defined by the RequirementChecker component
     * @param string $sourcePhar   source PHAR location; This PHAR is going to be copied over to the image so the path
     *                             should either be absolute or relative to the location of the Dockerfile
     */
    public static function createForRequirements(array $requirements, string $sourcePhar): self
    {
        return new self(
            self::retrievePhpImageName($requirements),
            self::retrievePhpExtensions($requirements),
            $sourcePhar,
        );
    }

    /**
     * @param string[] $extensions
     * @param string   $sourcePhar source PHAR location; This PHAR is going to be copied over to the image so the path
     *                             should either be absolute or relative to the location of the Dockerfile
     */
    public function __construct(
        string $image,
        array $extensions,
        private readonly string $sourcePhar,
    ) {
        Assert::inArray($image, self::PHP_DOCKER_IMAGES);
        Assert::allString($extensions);

        $this->image = $image;
        $this->extensions = $extensions;
    }

    public function generateStub(): string
    {
        $requiredExtensions = 0 === count($this->extensions)
            ? ''
            : sprintf(
                'RUN install-php-extensions %s%s',
                implode(' ', $this->extensions),
                PHP_EOL,
            );

        return strtr(
            self::FILE_TEMPLATE,
            [
                '__BASE_PHP_IMAGE_TOKEN__' => $this->image,
                '__PHAR_FILE_PATH_TOKEN__' => $this->sourcePhar,
                '__PHAR_FILE_NAME_TOKEN__' => basename($this->sourcePhar),
                '__REQUIRED_EXTENSIONS__' => $requiredExtensions,
            ],
        );
    }

    private static function retrievePhpImageName(array $requirements): string
    {
        $conditions = array_column(
            array_filter(
                $requirements,
                static fn (array $requirement): bool => 'php' === $requirement['type'],
            ),
            'condition',
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
                'Could not find a suitable Docker base image for the PHP constraint(s) "%s". Images available: "%s".',
                implode('", "', $conditions),
                implode('", "', self::PHP_DOCKER_IMAGES),
            ),
        );
    }

    /**
     * @return string[]
     */
    private static function retrievePhpExtensions(array $requirements): array
    {
        return array_unique(
            array_column(
                array_filter(
                    $requirements,
                    static fn (array $requirement): bool => 'extension' === $requirement['type'],
                ),
                'condition',
            ),
        );
    }
}
