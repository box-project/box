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

namespace KevinGH\Box\Pharaoh;

use KevinGH\Box\Test\RequiresPharReadonlyOff;
use Phar;
use PharData;
use PHPUnit\Framework\TestCase;
use function get_class;
use const DIRECTORY_SEPARATOR;

/**
 * @covers \KevinGH\Box\Pharaoh\Pharaoh
 * @runTestsInSeparateProcesses
 *
 * @internal
 */
final class PharaohTest extends TestCase
{
    use RequiresPharReadonlyOff;

    private const FIXTURES_DIR = __DIR__.'/../../fixtures/info';

    protected function setUp(): void
    {
        $this->markAsSkippedIfPharReadonlyIsOn();
    }

    /**
     * @dataProvider fileProvider
     */
    public function test_it_can_be_instantiated(
        string $fileName,
        string $expectedClassName
    ): void {
        $file = self::FIXTURES_DIR.DIRECTORY_SEPARATOR.$fileName;

        $pharInfo = new Pharaoh($file);

        self::assertSame($file, $pharInfo->getFile());
        self::assertSame($fileName, $pharInfo->getFileName());
        self::assertSame($expectedClassName, get_class($pharInfo->getPhar()));
    }

    public static function fileProvider(): iterable
    {
        yield 'simple PHAR' => [
            'simple-phar.phar',
            Phar::class,
        ];

        yield 'simple PHAR without the extension' => [
            'simple-phar',
            Phar::class,
        ];

        yield 'compressed archive' => [
            'simple-phar.tar.bz2',
            PharData::class,
        ];
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

    public function test_it_preserves_the_phar_signature(): void
    {
        $file = self::FIXTURES_DIR.'/simple-phar.phar';

        $phar = new Phar($file);
        $pharaoh = new Pharaoh($file);

        self::assertEquals($phar->getSignature(), $pharaoh->getSignature());
        self::assertNotFalse($pharaoh->getSignature());
    }

    public function test_it_preserves_the_absence_of_signature(): void
    {
        $file = self::FIXTURES_DIR.'/simple-phar.tar.bz2';

        $pharaoh = new Pharaoh($file);

        self::assertFalse($pharaoh->getSignature());
    }
}
