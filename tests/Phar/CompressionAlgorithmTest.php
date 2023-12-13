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

namespace KevinGH\Box\Phar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use function array_map;

/**
 * @internal
 */
#[CoversClass(CompressionAlgorithm::class)]
final class CompressionAlgorithmTest extends TestCase
{
    #[DataProvider('compressionAlgorithmLabelsProvider')]
    public function test_it_can_tell_be_created_from_its_label(
        ?string $label,
        CompressionAlgorithm $expected
    ): void {
        $actual = CompressionAlgorithm::fromLabel($label);

        self::assertSame($expected, $actual);

        if (null !== $label) {
            self::assertSame($label, $actual->name);
        }
    }

    public static function compressionAlgorithmLabelsProvider(): iterable
    {
        $compressionAlgorithmReflection = new ReflectionEnum(CompressionAlgorithm::class);

        foreach ($compressionAlgorithmReflection->getCases() as $compressionAlgorithmCase) {
            yield [
                $compressionAlgorithmCase->getName(),
                $compressionAlgorithmCase->getValue(),
            ];
        }

        yield [
            null,
            CompressionAlgorithm::NONE,
        ];
    }

    public function test_it_can_list_all_its_labels(): void
    {
        $compressionAlgorithmReflection = new ReflectionEnum(CompressionAlgorithm::class);

        $expected = array_map(
            static fn (ReflectionEnumBackedCase $compressionAlgorithmCase) => $compressionAlgorithmCase->getName(),
            $compressionAlgorithmReflection->getCases(),
        );

        $actual = CompressionAlgorithm::getLabels();

        self::assertSame($expected, $actual);
    }

    #[DataProvider('compressionAlgorithmProvider')]
    public function test_it_can_tell_what_php_extension_is_required_for_a_given_compression_algorithm(
        CompressionAlgorithm $compressionAlgorithm
    ): void {
        $compressionAlgorithm->getRequiredExtension();

        // We just want to make sure all the cases are listed here.
        // Testing the actual values would only be redundant.
        $this->addToAssertionCount(1);
    }

    public static function compressionAlgorithmProvider(): iterable
    {
        foreach (CompressionAlgorithm::cases() as $compressionAlgorithm) {
            yield [$compressionAlgorithm];
        }
    }
}
