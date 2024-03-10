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

use KevinGH\Box\Composer\Artifact\ComposerJson;
use KevinGH\Box\Composer\Artifact\ComposerLock;
use KevinGH\Box\Phar\CompressionAlgorithm;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Webmozart\Assert\Assert;
use function str_replace;
use function var_export;

/**
 * @private
 */
final readonly class RequirementsDumper
{
    private const REQUIREMENTS_CONFIG_TEMPLATE = <<<'PHP'
        <?php

        return '__CONFIG__';
        PHP;

    private const REQUIREMENT_CHECKER_PATH = __DIR__.'/../../res/requirement-checker';

    public function __construct(
        private AppRequirementsFactory $requirementsFactory,
    ) {
    }

    /**
     * @return list<array{string, string}>
     */
    public function dump(
        ?ComposerJson $composerJson,
        ?ComposerLock $composerLock,
        CompressionAlgorithm $compressionAlgorithm,
    ): array {
        Assert::directory(
            self::REQUIREMENT_CHECKER_PATH,
            'Expected the requirement checker to have been dumped',
        );

        $filesWithContents = [
            $this->dumpRequirementsConfig(
                $composerJson ?? new ComposerJson('', []),
                $composerLock ?? new ComposerLock('', []),
                $compressionAlgorithm,
            ),
        ];

        /** @var SplFileInfo[] $requirementCheckerFiles */
        $requirementCheckerFiles = Finder::create()
            ->files()
            ->in(self::REQUIREMENT_CHECKER_PATH);

        foreach ($requirementCheckerFiles as $file) {
            $filesWithContents[] = [
                $file->getRelativePathname(),
                $file->getContents(),
            ];
        }

        return $filesWithContents;
    }

    private function dumpRequirementsConfig(
        ComposerJson $composerJson,
        ComposerLock $composerLock,
        CompressionAlgorithm $compressionAlgorithm,
    ): array {
        $requirements = $this->requirementsFactory->create(
            $composerJson,
            $composerLock,
            $compressionAlgorithm,
        );

        return [
            '.requirements.php',
            str_replace(
                '\'__CONFIG__\'',
                var_export($requirements->toArray(), true),
                self::REQUIREMENTS_CONFIG_TEMPLATE,
            ),
        ];
    }
}
