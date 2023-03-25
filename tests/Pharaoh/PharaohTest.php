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

namespace KevinGH\RequirementChecker\Pharaoh;

use KevinGH\Box\Pharaoh\Pharaoh;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Pharaoh\Pharaoh
 *
 * @internal
 */
final class PharaohTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__.'/../../fixtures/info';

    public function test_it_can_be_instantiated(): void
    {
        $file = self::FIXTURES_DIR.'/simple-phar.phar';
        $pharInfo = new Pharaoh($file);

        self::assertSame($file, $pharInfo->getFile());
        self::assertSame('simple-phar.phar', $pharInfo->getFileName());
    }

    public function test_it_cleans_itself_up_upon_destruction(): void
    {
        $pharInfo = new Pharaoh(self::FIXTURES_DIR.'/simple-phar.phar');

        $tmp = $pharInfo->getTmp();

        self::assertDirectoryExists($tmp);

        unset($pharInfo);

        self::assertDirectoryDoesNotExist($tmp);
    }

    public function test_it_can_create_two_instances_of_the_same_phar(): void
    {
        $file = self::FIXTURES_DIR.'/simple-phar.phar';

        $pharInfoA = new Pharaoh($file);
        $pharInfoB = new Pharaoh($file);

        self::assertNotSame($pharInfoA->getPhar(), $pharInfoB->getPhar());
    }
}
