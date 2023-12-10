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

namespace KevinGH\RequirementChecker;

use Error;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Requirement::class)]
class RequirementTest extends TestCase
{
    public function test_it_can_be_created(): void
    {
        $requirement = new Requirement(
            $check = new IsPhpVersionFulfilled('>=5.3'),
            'Test message',
            'Help message'
        );

        self::assertSame($check, $requirement->getIsFullfilledChecker());
        self::assertTrue($requirement->isFulfilled());
        self::assertSame('Test message', $requirement->getTestMessage());
        self::assertSame('Help message', $requirement->getHelpText());
    }

    public function test_it_evaluates_the_check_lazily(): void
    {
        $requirement = new Requirement(
            $check = new class() implements IsFulfilled {
                public function __invoke(): bool
                {
                    throw new Error();
                }
            },
            'Test message',
            'Help message'
        );

        self::assertSame($check, $requirement->getIsFullfilledChecker());

        try {
            $requirement->isFulfilled();

            self::fail('Expected exception to be thrown.');
        } catch (Error) {
            self::assertTrue(true);
        }
    }

    public function test_it_evaluates_the_check_only_once(): void
    {
        $requirement = new Requirement(
            new class() implements IsFulfilled {
                private bool $calledOnce = false;

                public function __invoke(): bool
                {
                    $result = $this->calledOnce;
                    $this->calledOnce = true;

                    return $result;
                }
            },
            'Test message',
            'Help message'
        );

        self::assertFalse($requirement->isFulfilled());
        self::assertFalse($requirement->isFulfilled());    // Would have given `true` if it was evaluated a second time
    }
}
