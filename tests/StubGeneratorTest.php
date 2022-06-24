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

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\StubGenerator
 */
class StubGeneratorTest extends TestCase
{
    private StubGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new StubGenerator();
    }

    public function test_it_can_be_created(): void
    {
        $this->assertInstanceOf(
            StubGenerator::class,
            StubGenerator::create(),
        );
    }

    public function test_it_can_generate_a_stub_with_the_default_config(): void
    {
        $expected = <<<'STUB'
            <?php

            require 'phar://' . __FILE__ . '/.box/bin/check-requirements.php';

            __HALT_COMPILER(); ?>

            STUB;
        $actual = $this->generator->generateStub();

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_generate_an_empty_stub(): void
    {
        $this->generator->checkRequirements(false);

        $expected = <<<'STUB'
            <?php

            // No PHAR config

            __HALT_COMPILER(); ?>

            STUB;
        $actual = $this->generator->generateStub();

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_generate_a_stub_with_a_custom_banner(): void
    {
        $this->generator->banner(
            <<<'TEXT'
                Custom Banner

                Yolo
                TEXT,
        );

        $expected = <<<'STUB'
            <?php

            /*
             * Custom Banner
             *
             * Yolo
             */

            require 'phar://' . __FILE__ . '/.box/bin/check-requirements.php';

            __HALT_COMPILER(); ?>

            STUB;
        $actual = $this->generator->generateStub();

        $this->assertSame($expected, $actual);

        $this->generator->checkRequirements(false);

        $expected = <<<'STUB'
            <?php

            /*
             * Custom Banner
             *
             * Yolo
             */

            // No PHAR config

            __HALT_COMPILER(); ?>

            STUB;
        $actual = $this->generator->generateStub();

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_generate_a_stub_with_a_custom_shebang(): void
    {
        $this->generator->shebang('#!/usr/local/bin/env php');

        $expected = <<<'STUB'
            #!/usr/local/bin/env php
            <?php

            require 'phar://' . __FILE__ . '/.box/bin/check-requirements.php';

            __HALT_COMPILER(); ?>

            STUB;
        $actual = $this->generator->generateStub();

        $this->assertSame($expected, $actual);

        $this->generator->checkRequirements(false);

        $expected = <<<'STUB'
            #!/usr/local/bin/env php
            <?php

            // No PHAR config

            __HALT_COMPILER(); ?>

            STUB;
        $actual = $this->generator->generateStub();

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_generate_a_stub_without_a_shebang(): void
    {
        $this->generator->shebang(null);

        $expected = <<<'STUB'
            <?php

            require 'phar://' . __FILE__ . '/.box/bin/check-requirements.php';

            __HALT_COMPILER(); ?>

            STUB;
        $actual = $this->generator->generateStub();

        $this->generator->checkRequirements(false);

        $this->assertSame($expected, $actual);

        $expected = <<<'STUB'
            <?php

            // No PHAR config

            __HALT_COMPILER(); ?>

            STUB;
        $actual = $this->generator->generateStub();

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_generate_a_stub_with_a_custom_alias(): void
    {
        $this->generator->alias('acme.phar');

        $expected = <<<'STUB'
            <?php

            Phar::mapPhar('acme.phar');

            require 'phar://acme.phar/.box/bin/check-requirements.php';

            __HALT_COMPILER(); ?>

            STUB;
        $actual = $this->generator->generateStub();

        $this->assertSame($expected, $actual);

        $this->generator->checkRequirements(false);

        $expected = <<<'STUB'
            <?php

            Phar::mapPhar('acme.phar');

            __HALT_COMPILER(); ?>

            STUB;
        $actual = $this->generator->generateStub();

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_generate_a_stub_with_no_alias(): void
    {
        $this->generator->alias(null);

        $expected = <<<'STUB'
            <?php

            require 'phar://' . __FILE__ . '/.box/bin/check-requirements.php';

            __HALT_COMPILER(); ?>

            STUB;
        $actual = $this->generator->generateStub();

        $this->assertSame($expected, $actual);

        $this->generator->checkRequirements(false);

        $expected = <<<'STUB'
            <?php

            // No PHAR config

            __HALT_COMPILER(); ?>

            STUB;
        $actual = $this->generator->generateStub();

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_generate_a_stub_with_an_index_file(): void
    {
        $this->generator->index('acme.php');

        $expected = <<<'STUB'
            <?php

            require 'phar://' . __FILE__ . '/.box/bin/check-requirements.php';

            require 'phar://' . __FILE__ . '/acme.php';

            __HALT_COMPILER(); ?>

            STUB;
        $actual = $this->generator->generateStub();

        $this->assertSame($expected, $actual);

        $this->generator->checkRequirements(false);

        $expected = <<<'STUB'
            <?php

            require 'phar://' . __FILE__ . '/acme.php';

            __HALT_COMPILER(); ?>

            STUB;
        $actual = $this->generator->generateStub();

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_generate_a_stub_configuring_the_phar_to_intercept_filesystem_stat_functions(): void
    {
        $this->generator->intercept(true);

        $expected = <<<'STUB'
            <?php

            Phar::interceptFileFuncs();

            require 'phar://' . __FILE__ . '/.box/bin/check-requirements.php';

            __HALT_COMPILER(); ?>

            STUB;
        $actual = $this->generator->generateStub();

        $this->assertSame($expected, $actual);

        $this->generator->checkRequirements(false);

        $expected = <<<'STUB'
            <?php

            Phar::interceptFileFuncs();

            __HALT_COMPILER(); ?>

            STUB;
        $actual = $this->generator->generateStub();

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_generate_a_complete_stub(): void
    {
        $this->generator
            ->banner(
                <<<'TEXT'
                    Custom Banner

                    Yolo
                    TEXT,
            )
            ->shebang('#!/usr/local/bin/env php')
            ->alias('test.phar')
            ->index('index.php')
            ->intercept(true)
            ->checkRequirements(true)
        ;

        $expected = <<<'STUB'
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

            require 'phar://test.phar/index.php';

            __HALT_COMPILER(); ?>

            STUB;
        $actual = $this->generator->generateStub();

        $this->assertSame($expected, $actual);

        $this->generator->checkRequirements(false);

        $expected = <<<'STUB'
            #!/usr/local/bin/env php
            <?php

            /*
             * Custom Banner
             *
             * Yolo
             */

            Phar::mapPhar('test.phar');
            Phar::interceptFileFuncs();

            require 'phar://test.phar/index.php';

            __HALT_COMPILER(); ?>

            STUB;
        $actual = $this->generator->generateStub();

        $this->assertSame($expected, $actual);
    }

    public function test_test_it_cannot_generate_the_stub_without_shebang(): void
    {
        try {
            $this->generator->shebang('');

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Cannot use an empty string for the shebang.',
                $exception->getMessage(),
            );
        }
    }
}
