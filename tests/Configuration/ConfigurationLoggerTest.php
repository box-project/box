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

namespace KevinGH\Box\Configuration;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ConfigurationLogger::class)]
class ConfigurationLoggerTest extends TestCase
{
    private ConfigurationLogger $logs;

    protected function setUp(): void
    {
        $this->logs = new ConfigurationLogger();
    }

    public function test_it_has_no_messages_by_default(): void
    {
        self::assertSame([], $this->logs->getRecommendations());
        self::assertSame([], $this->logs->getWarnings());
    }

    #[DataProvider('emptyMessageProvider')]
    public function test_it_cannot_accept_an_empty_message(string $message): void
    {
        try {
            $this->logs->addRecommendation($message);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Expected to have a message but a blank string was given instead.',
                $exception->getMessage(),
            );
        }

        try {
            $this->logs->addWarning($message);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Expected to have a message but a blank string was given instead.',
                $exception->getMessage(),
            );
        }
    }

    public function test_it_can_store_recommendations_and_warnings(): void
    {
        $this->logs->addRecommendation('First recommendation');
        $this->logs->addRecommendation('Second recommendation');

        $this->logs->addWarning('First warning');
        $this->logs->addWarning('Second warning');

        self::assertSame(
            [
                'First recommendation',
                'Second recommendation',
            ],
            $this->logs->getRecommendations(),
        );

        self::assertSame(
            [
                'First warning',
                'Second warning',
            ],
            $this->logs->getWarnings(),
        );
    }

    public function test_it_removes_duplicated_messages(): void
    {
        $this->logs->addRecommendation('First recommendation');
        $this->logs->addRecommendation('First recommendation');
        $this->logs->addRecommendation('Second recommendation');

        $this->logs->addWarning('First warning');
        $this->logs->addWarning('First warning');
        $this->logs->addWarning('Second warning');

        self::assertSame(
            [
                'First recommendation',
                'Second recommendation',
            ],
            $this->logs->getRecommendations(),
        );

        self::assertSame(
            [
                'First warning',
                'Second warning',
            ],
            $this->logs->getWarnings(),
        );
    }

    public static function emptyMessageProvider(): iterable
    {
        yield [''];
        yield [' '];
    }
}
