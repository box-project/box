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

namespace KevinGH\Box\Composer;

use Webmozart\Assert\Assert;
use function array_filter;
use function array_values;
use function in_array;
use function is_array;
use function is_string;

final class InstalledJsonFile extends ComposerFile
{
    public function excludeDevPackages(): self
    {
        $path = $this->getPath();
        $decodedContents = $this->getDecodedContents();

        if ($decodedContents['dev'] === false) {
            // Nothing to do.
            return $this;
        }

        $devPackageNames = $decodedContents['dev-package-names'];

        $newDecodedContents = [
            'packages' => array_values(
                array_filter(
                    $decodedContents['packages'],
                    static fn (array $package) => !in_array($package['name'], $devPackageNames, true),
                ),
            ),
            'dev' => false,
            'dev-package-names' => [],
        ];

        return new self($path, $newDecodedContents);
    }
}
