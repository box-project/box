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

namespace KevinGH\Box\Cyclonedx;

use CycloneDX\Core\Collections\ComponentRepository;
use CycloneDX\Core\Enums\ComponentType;
use CycloneDX\Core\Factories\LicenseFactory;
use CycloneDX\Core\Models\Bom;
use CycloneDX\Core\Models\Component;
use CycloneDX\Core\Models\Property;
use CycloneDX\Core\Models\Tool;
use CycloneDX\Core\Serialization\JSON\NormalizerFactory as JsonNormalizerFactory;
use CycloneDX\Core\Serialization\JsonSerializer;
use CycloneDX\Core\Serialization\Serializer;
use CycloneDX\Core\Spec\_SpecProtocol;
use CycloneDX\Core\Spec\SpecFactory;
use CycloneDX\Core\Spec\Version;
use CycloneDX\Core\Utils\BomUtility;
use DateTimeImmutable;
use DomainException;
use KevinGH\Box\RequirementChecker\DecodedComposerJson;
use PackageUrl\PackageUrl;
use ValueError;
use function array_column;
use function count;
use function explode;
use function implode;
use function sprintf;

/**
 * MIT License.
 *
 * Copyright (c) 2022-2023 Laurent Laville
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author Laurent Laville
 * @since Release 3.0.0
 */
final class ManifestFactory
{
    // https://github.com/CycloneDX/specification/tree/master#media-types
    private const SBOM_FORMAT = 'json';
    // https://github.com/CycloneDX/specification/tree/master#release-history
    private const SBOM_VERSION = '1.5';

    public static function create(
        string $boxVersion,
        string $sbomVersion = self::SBOM_VERSION,
        string $sbomFormat = self::SBOM_FORMAT,
    ): self {
        $version = self::getVersion($sbomVersion);
        $spec = SpecFactory::makeForVersion($version);
        $serializer = new JsonSerializer(
            new JsonNormalizerFactory($spec),
        );

        return new self(
            $version,
            $sbomFormat,
            $serializer,
            $boxVersion,
        );
    }

    public function __construct(
        private Version $version,
        private string $format,
        private Serializer $serializer,
        private string $boxVersion,
    ) {
    }

    public function generate(
        array $composerJsonDecodedContent,
        array $installedPhpDecodedContent,
    ): string {
        dd($installedPhpDecodedContent);
        $rootPackage = $installedPhpDecodedContent['root'];
        $version = self::getRootPackageVersion($rootPackage);

        $bom = new Bom();
        // TODO: this is an issue for reproducible builds
        $bom->setSerialNumber(BomUtility::randomSerialNumber());

        $component = self::createComponent(
            $rootPackage,
            $version,
            $composerJsonDecodedContent['description'] ?? '',
        );

        $packageUrl = new PackageUrl('composer', $rootPackage['name']);
        $packageUrl->setVersion($version);
        $component->setPackageUrl($packageUrl);
        $component->setBomRefValue((string) $packageUrl);

        // scope
        if (isset($composerJsonDecodedContent['license'])) {
            $licenseFactory = new LicenseFactory();

            if (!empty($composerJsonDecodedContent['license'])) {
                $component->getLicenses()->addItems(
                    $licenseFactory->makeFromString($composerJsonDecodedContent['license'])
                );
            }
        }

        // metadata
        // TODO: check if this is necessary?
        $boxTool = new Tool();
        $boxTool->setVendor('box-project');
        $boxTool->setName('box');
        $boxTool->setVersion($this->boxVersion);

        // TODO: the time here is also problematic, should probably take the one from the Compile command.
        // TODO: also an issue for reproducible builds
        $bom->getMetadata()->setTimestamp(new DateTimeImmutable());
        $bom->getMetadata()->getProperties()->addItems(
            new Property('specVersion', $this->version->value),
            new Property('bomFormat', $this->format),
        );

        $componentRepository = $bom->getComponents();

        self::addInstalledPackages(
            $installedPhpDecodedContent['versions'],
            $rootPackage['name'],
            $componentRepository,
        );

        return $this->serializer->serialize($bom, true);
    }

    private static function getVersion(string $version): Version
    {
        try {
            return Version::from(self::SBOM_VERSION);
        } catch (ValueError) {
            throw new DomainException(
                sprintf(
                    'Unsupported spec version "%s" for SBOM format. Expected one of : "%s". Got "%s".',
                    $specVersion,
                    implode(
                        '", "',
                        array_column(Version::cases(), 'value'),
                    ),
                    $version,
                ),
            );
        }
    }

    private static function getRootPackageVersion(array $rootPackage): string
    {
        if (!empty($rootPackage['aliases'])) {
            return sprintf(
                '%s@%s',
                $rootPackage['aliases'][0],
                mb_substr($rootPackage['reference'], 0, 7)
            );
        }

        if (isset($rootPackage['pretty_version'])) {
            return sprintf(
                '%s@%s',
                $rootPackage['pretty_version'],
                mb_substr($rootPackage['reference'], 0, 7)
            );
        }

        return $rootPackage['version'];
    }

    /**
     * @param mixed $rootPackage
     */
    private static function createComponent(
        array $rootPackage,
        string $version,
        string $description,
    ): Component {
        [$vendor, $name] = explode('/', $rootPackage['name']);

        $type = 'library' === $rootPackage['type'] ? ComponentType::Library : ComponentType::Application;

        $component = new Component($type, $name);
        $component->setVersion($version);
        $component->setGroup($vendor);
        $component->setDescription($description);

        return $component;
    }

    private static function addInstalledPackages(
        array $versions,
        string $packageName,
        ComponentRepository $componentRepository,
    ): void {
        foreach ($versions as $package => $values) {
            if ($package === $packageName) {
                // does not include root package
                continue;
            }

            if (!isset($values['pretty_version'])) {
                // it's a virtual package
                continue;
            }

            if (0 === count($values['aliases'])) {
                $version = $values['pretty_version'];
            } else {
                $version = sprintf(
                    '%s@%s',
                    $values['pretty_version'],
                    mb_substr($values['reference'], 0, 7)
                );
            }

            [$vendor, $name] = explode('/', $package);

            $type = 'library' === $values['type'] ? ComponentType::Library : ComponentType::Application;

            $component = new Component($type, $name);
            $component->setVersion($version);
            $component->setGroup($vendor);

            $packageUrl = new PackageUrl('composer', $package);
            $packageUrl->setVersion($version);
            $component->setPackageUrl($packageUrl);
            $component->setBomRefValue((string) $packageUrl);

            $componentRepository->addItems($component);
        }
    }
}
