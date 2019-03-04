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

namespace KevinGH\Box\RequirementChecker;

use Assert\Assertion;
use function KevinGH\Box\FileSystem\file_contents;
use function str_replace;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use function var_export;

/**
 * @private
 */
final class RequirementsDumper
{
    private const REQUIREMENTS_CONFIG_TEMPLATE = <<<'PHP'
<?php

return '__CONFIG__';
PHP;

    private const REQUIREMENT_CHECKER_PATH = __DIR__.'/../../.requirement-checker';

    /**
     * @return string[][]
     */
    public static function dump(array $decodedComposerJsonContents, array $decodedComposerLockContents, ?int $compressionAlgorithm): array
    {
        Assertion::directory(self::REQUIREMENT_CHECKER_PATH, 'Expected the requirement checker to have been dumped');

        $filesWithContents = [
            self::dumpRequirementsConfig($decodedComposerJsonContents, $decodedComposerLockContents, $compressionAlgorithm),
        ];

        /** @var SplFileInfo[] $requirementCheckerFiles */
        $requirementCheckerFiles = Finder::create()
            ->files()
            ->in(self::REQUIREMENT_CHECKER_PATH)
        ;

        foreach ($requirementCheckerFiles as $file) {
            $filesWithContents[] = [
                $file->getRelativePathname(),
                file_contents($file->getPathname()),
            ];
        }

        return $filesWithContents;
    }

    private static function dumpRequirementsConfig(
        array $composerJsonDecodedContents,
        array $composerLockDecodedContents,
        ?int $compressionAlgorithm
    ): array {
        $config = AppRequirementsFactory::create($composerJsonDecodedContents, $composerLockDecodedContents, $compressionAlgorithm);

        return [
            '.requirements.php',
            str_replace(
                '\'__CONFIG__\'',
                var_export($config, true),
                self::REQUIREMENTS_CONFIG_TEMPLATE
            ),
        ];
    }
}
