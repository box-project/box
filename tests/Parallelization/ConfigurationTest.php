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

namespace KevinGH\Box\Parallelization;

use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use KevinGH\Box\Compactor\Compactors;
use KevinGH\Box\MapFile;
use PhpParser\Node\Name\FullyQualified;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Parallelization\Configuration
 * @internal
 */
final class ConfigurationTest extends TestCase
{
    /**
     * @dataProvider configurationProvider
     */
    public function test_it_can_be_serialized_and_deserialized(Configuration $configuration): void
    {
        $unserializedConfiguration = Configuration::unserialize($configuration->serialize());

        self::assertEquals($configuration, $unserializedConfiguration);
    }

    public static function configurationProvider(): iterable
    {
        yield 'nominal' => [
            new Configuration(
                [
                    '/path/to/file1.php',
                    '/path/to/file2.php',
                ],
                new MapFile('/path/to/base', []),
                new Compactors(),
            ),
        ];
    }
}
