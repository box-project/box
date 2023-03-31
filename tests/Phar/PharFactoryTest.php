<?php

declare(strict_types=1);

namespace KevinGH\Box\Phar;

use KevinGH\Box\Pharaoh\InvalidPhar;
use Phar;
use PharData;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Phar\PharFactory
 *
 * @preserveGlobalState disabled
 * @runTestsInSeparateProcesses
 */
final class PharFactoryTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__.'/../../fixtures/info';

    public function test_it_can_create_phars(): void
    {
        $phar = PharFactory::createPhar(self::FIXTURES_DIR.'/simple-phar.phar');

        self::assertSame(Phar::class, $phar::class);
    }

    public function test_it_can_create_phar_datas(): void
    {
        $pharData = PharFactory::createPharData(self::FIXTURES_DIR.'/simple-phar.tar.gz');

        self::assertSame(PharData::class, $pharData::class);
    }

    /**
     * @dataProvider invalidPharProvider
     */
    public function test_it_fails_with_a_comprehensive_error_when_cannot_create_a_phar(
        string $file,
        string $expectedExceptionMessageRegex,
    ): void
    {
        $this->expectException(InvalidPhar::class);
        $this->expectExceptionMessageMatches($expectedExceptionMessageRegex);

        PharFactory::createPhar($file);
    }

    public static function invalidPharProvider(): iterable
    {
        yield 'URL of a valid PHAR' => [
            'https://github.com/box-project/box/releases/download/4.3.8/box.phar',
            '/^Cannot create a PHAR object from a URL like ".+"\. PHAR objects can only be created from local files\.$/',
        ];

        yield 'x' => [
            self::FIXTURES_DIR.'/../phar/Empty.pdf',
            '/^foo\.$/',
        ];

        // TODO:
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1330
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1343
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1351
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1430
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#LL1565C45-L1565C45
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1669
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1694
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1705
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1708
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1721
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1730
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1735
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1743
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1750
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1763
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L529
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L853
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L874
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L892
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L903
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L921
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L931
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L948
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L958
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L975
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L985
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1002
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1012
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1024
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1033
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1064
    }

    public function test_it_fails_with_a_comprehensive_error_when_cannot_create_a_phar_data(): void
    {
$this->markTestSkipped();
    }
}
