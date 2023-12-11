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
use UnexpectedValueException;
use function array_map;

/**
 * @internal
 */
#[CoversClass(SigningAlgorithm::class)]
final class SigningAlgorithmTest extends TestCase
{
    #[DataProvider('signingAlgorithmLabelsProvider')]
    public function test_it_can_tell_be_created_from_its_label(
        ?string $label,
        SigningAlgorithm $expected
    ): void {
        $actual = SigningAlgorithm::fromLabel($label);

        self::assertSame($expected, $actual);

        if (null !== $label) {
            self::assertSame($label, $actual->name);
        }
    }

    public static function signingAlgorithmLabelsProvider(): iterable
    {
        $signingAlgorithmReflection = new ReflectionEnum(SigningAlgorithm::class);

        foreach ($signingAlgorithmReflection->getCases() as $signingAlgorithmCase) {
            yield [
                $signingAlgorithmCase->getName(),
                $signingAlgorithmCase->getValue(),
            ];
        }
    }

    public function test_it_can_list_all_its_labels(): void
    {
        $signingAlgorithmReflection = new ReflectionEnum(SigningAlgorithm::class);

        $expected = array_map(
            static fn (ReflectionEnumBackedCase $signingAlgorithmCase) => $signingAlgorithmCase->getName(),
            $signingAlgorithmReflection->getCases(),
        );

        $actual = SigningAlgorithm::getLabels();

        self::assertSame($expected, $actual);
    }

    public function test_it_cannot_create_an_unsupported_signing_algorithm(): void
    {
        $this->expectExceptionObject(
            new UnexpectedValueException(
                'The signing algorithm "UNKNOWN" is not supported by your current PHAR version.',
            ),
        );

        SigningAlgorithm::fromLabel('UNKNOWN');
    }
}
