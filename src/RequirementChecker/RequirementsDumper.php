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

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use function KevinGH\Box\FileSystem\file_contents;
use function str_replace;
use function var_export;

/**
 * @private
 */
final class RequirementsDumper
{
    public const CHECK_FILE_NAME = 'check_requirements.php';

    private const REQUIREMENTS_CHECKER_TEMPLATE = <<<'PHP'
<?php

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

require 'bin/check-requirements.php';

PHP;

    private const REQUIREMENTS_CONFIG_TEMPLATE = <<<'PHP'
<?php

return __CONFIG__;
PHP;

    private const REQUIRMEMENT_CHECKER_PATH = __DIR__.'/../../.requirement-checker';

    /**
     * @return string[][]
     */
    public static function dump(array $composerLockDecodedContents): array
    {
        $filesWithContents = [
            self::dumpRequirementsConfig($composerLockDecodedContents),
            [self::CHECK_FILE_NAME, self::REQUIREMENTS_CHECKER_TEMPLATE],
        ];

        /** @var SplFileInfo[] $requirementCheckerFiles */
        $requirementCheckerFiles = Finder::create()
            ->files()
            ->in(self::REQUIRMEMENT_CHECKER_PATH)
        ;

        foreach ($requirementCheckerFiles as $file) {
            $filesWithContents[] = [
                $file->getRelativePathname(),
                file_contents($file->getPathname()),
            ];
        }

        return $filesWithContents;
    }

    private static function dumpRequirementsConfig(array $composerLockDecodedContents): array
    {
        $config = AppRequirementsFactory::create($composerLockDecodedContents);

        return [
            '.requirements.php',
            str_replace(
                '__CONFIG__',
                var_export($config, true),
                self::REQUIREMENTS_CONFIG_TEMPLATE
            ),
        ];
    }
}
