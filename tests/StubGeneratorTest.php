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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use function array_merge;
use function array_values;

/**
 * @internal
 */
#[CoversClass(StubGenerator::class)]
class StubGeneratorTest extends TestCase
{
    #[DataProvider('valuesProvider')]
    public function test_it_can_generate_a_stub(
        ?string $alias,
        ?string $banner,
        ?string $index,
        bool $intercept,
        ?string $shebang,
        bool $checkRequirements,
        string $expected,
    ): void {
        $actual = StubGenerator::generateStub(
            $alias,
            $banner,
            $index,
            $intercept,
            $shebang,
            $checkRequirements,
        );

        self::assertSame($expected, $actual);
    }

    public static function valuesProvider(): iterable
    {
        $defaultValues = self::createDefaultValues();
        $createValues = static fn (array $values = []) => array_values(
            array_merge($defaultValues, $values),
        );

        yield 'default' => [
            ...$createValues(),
            <<<'STUB'
                <?php

                require 'phar://' . __FILE__ . '/.box/bin/check-requirements.php';

                __HALT_COMPILER(); ?>

                STUB,
        ];

        yield 'requirement-checker disabled' => [
            ...$createValues(['checkRequirements' => false]),
            <<<'STUB'
                <?php

                // No PHAR config

                __HALT_COMPILER(); ?>

                STUB,
        ];

        yield 'custom banner' => [
            ...$createValues([
                'banner' => <<<'TEXT'
                    Custom Banner

                    Yolo
                    TEXT,
            ]),
            <<<'STUB'
                <?php

                /*
                 * Custom Banner
                 *
                 * Yolo
                 */

                require 'phar://' . __FILE__ . '/.box/bin/check-requirements.php';

                __HALT_COMPILER(); ?>

                STUB,
        ];

        yield 'custom banner without requirement checker' => [
            ...$createValues([
                'checkRequirements' => false,
                'banner' => <<<'TEXT'
                    Custom Banner

                    Yolo
                    TEXT,
            ]),
            <<<'STUB'
                <?php

                /*
                 * Custom Banner
                 *
                 * Yolo
                 */

                // No PHAR config

                __HALT_COMPILER(); ?>

                STUB,
        ];

        yield 'custom shebang' => [
            ...$createValues(['shebang' => '#!/usr/local/bin/env php']),
            <<<'STUB'
                #!/usr/local/bin/env php
                <?php

                require 'phar://' . __FILE__ . '/.box/bin/check-requirements.php';

                __HALT_COMPILER(); ?>

                STUB,
        ];

        yield 'custom shebang without requirement checker' => [
            ...$createValues([
                'checkRequirements' => false,
                'shebang' => '#!/usr/local/bin/env php',
            ]),
            <<<'STUB'
                #!/usr/local/bin/env php
                <?php

                // No PHAR config

                __HALT_COMPILER(); ?>

                STUB,
        ];

        yield 'custom alias' => [
            ...$createValues(['alias' => 'acme.phar']),
            <<<'STUB'
                <?php

                Phar::mapPhar('acme.phar');

                require 'phar://acme.phar/.box/bin/check-requirements.php';

                __HALT_COMPILER(); ?>

                STUB,
        ];

        yield 'custom alias without requirement checker' => [
            ...$createValues([
                'checkRequirements' => false,
                'alias' => 'acme.phar',
            ]),
            <<<'STUB'
                <?php

                Phar::mapPhar('acme.phar');

                __HALT_COMPILER(); ?>

                STUB,
        ];

        yield 'custom index file' => [
            ...$createValues(['index' => 'acme.php']),
            <<<'STUB'
                <?php

                require 'phar://' . __FILE__ . '/.box/bin/check-requirements.php';

                $_SERVER['SCRIPT_FILENAME'] = 'phar://' . __FILE__ . '/acme.php';
                require 'phar://' . __FILE__ . '/acme.php';

                __HALT_COMPILER(); ?>

                STUB,
        ];

        yield 'custom index without requirement checker' => [
            ...$createValues([
                'checkRequirements' => false,
                'index' => 'acme.php',
            ]),
            <<<'STUB'
                <?php

                $_SERVER['SCRIPT_FILENAME'] = 'phar://' . __FILE__ . '/acme.php';
                require 'phar://' . __FILE__ . '/acme.php';

                __HALT_COMPILER(); ?>

                STUB,
        ];

        yield 'intercept file functions' => [
            ...$createValues(['intercept' => true]),
            <<<'STUB'
                <?php

                Phar::interceptFileFuncs();

                require 'phar://' . __FILE__ . '/.box/bin/check-requirements.php';

                __HALT_COMPILER(); ?>

                STUB,
        ];

        yield 'intercept file functions without requirement checker' => [
            ...$createValues([
                'checkRequirements' => false,
                'intercept' => true,
            ]),
            <<<'STUB'
                <?php

                Phar::interceptFileFuncs();

                __HALT_COMPILER(); ?>

                STUB,
        ];

        yield 'nominal' => [
            ...$createValues([
                'banner' => <<<'TEXT'
                    Custom Banner

                    Yolo
                    TEXT,
                'shebang' => '#!/usr/local/bin/env php',
                'alias' => 'test.phar',
                'index' => 'index.php',
                'intercept' => true,
                'checkRequirements' => true,
            ]),
            <<<'STUB'
                #!/usr/local/bin/env php
                <?php

                /*
                 * Custom Banner
                 *
                 * Yolo
                 */

                Phar::mapPhar('test.phar');
                Phar::interceptFileFuncs();

                require 'phar://test.phar/.box/bin/check-requirements.php';

                $_SERVER['SCRIPT_FILENAME'] = 'phar://test.phar/index.php';
                require 'phar://test.phar/index.php';

                __HALT_COMPILER(); ?>

                STUB,
        ];

        yield 'nominal with requirement checker disabled' => [
            ...$createValues([
                'banner' => <<<'TEXT'
                    Custom Banner

                    Yolo
                    TEXT,
                'shebang' => '#!/usr/local/bin/env php',
                'alias' => 'test.phar',
                'index' => 'index.php',
                'intercept' => true,
                'checkRequirements' => false,
            ]),
            <<<'STUB'
                #!/usr/local/bin/env php
                <?php

                /*
                 * Custom Banner
                 *
                 * Yolo
                 */

                Phar::mapPhar('test.phar');
                Phar::interceptFileFuncs();

                $_SERVER['SCRIPT_FILENAME'] = 'phar://test.phar/index.php';
                require 'phar://test.phar/index.php';

                __HALT_COMPILER(); ?>

                STUB,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function createDefaultValues(): array
    {
        $generateStubParameters = (new ReflectionClass(StubGenerator::class))
            ->getMethod('generateStub')
            ->getParameters();

        $defaultValues = [];

        foreach ($generateStubParameters as $parameter) {
            $defaultValues[$parameter->getName()] = $parameter->getDefaultValue();
        }

        return $defaultValues;
    }
}
