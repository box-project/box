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

namespace KevinGH\Box\Annotation;

use Doctrine\Common\Annotations\DocLexer;
use KevinGH\Box\Annotation\Exception\InvalidArgumentException;
use KevinGH\Box\Annotation\Exception\UnexpectedTokenException;

/**
 * @covers \KevinGH\Box\Annotation\Sequence
 */
class SequenceTest extends TestTokens
{
    /**
     * {@inheritdoc}
     */
    public function getTokens(): array
    {
        $tokens = parent::getTokens();

        foreach ($tokens as $i => $list) {
            $tokens[$i] = [$list];
        }

        return $tokens;
    }

    public function testConstruct(): void
    {
        $tokens = [
            [123],
        ];

        $sequence = new Sequence($tokens);

        $this->assertEquals(
            $tokens,
            $this->getPropertyValue($sequence, 'tokens')
        );
    }

    public function testConstructWithTokens(): void
    {
        $tokens = [
            [123],
        ];

        $sequence = new Sequence(
            new Tokens($tokens)
        );

        $this->assertEquals(
            $tokens,
            $this->getPropertyValue($sequence, 'tokens')
        );
    }

    public function testConstructInvalid(): void
    {
        try {
            new Sequence(123);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The $tokens argument must be an array or instance of Tokens.',
                $exception->getMessage()
            );
        }
    }

    /**
     * @dataProvider getTokens
     *
     * @param mixed $tokens
     */
    public function testCurrent($tokens): void
    {
        $sequence = new Sequence($tokens);

        foreach ($tokens as $list) {
            $this->assertEquals($list, $sequence->current());

            $sequence->next();
        }
    }

    public function testCurrentUnexpected(): void
    {
        $sequence = new Sequence(
            [
                [DocLexer::T_IDENTIFIER, 'test'],
            ]
        );

        try {
            $sequence->current();

            $this->fail('Expected exception to be thrown.');
        } catch (UnexpectedTokenException $exception) {
            $this->assertSame(
                'Token #0 (100) is not expected here.',
                $exception->getMessage()
            );
        }
    }

    public function testCurrentUnexpectedDeeper(): void
    {
        $sequence = new Sequence(
            [
                [DocLexer::T_IDENTIFIER, 'test'],
                [DocLexer::T_OPEN_PARENTHESIS],
            ]
        );

        $sequence->next();

        try {
            $sequence->current();

            $this->fail('Expected exception to be thrown.');
        } catch (UnexpectedTokenException $exception) {
            $this->assertSame(
                'Token #1 (109) is not expected here.',
                $exception->getMessage()
            );
        }
    }

    public function testCurrentUnused(): void
    {
        $sequence = new Sequence(
            [
                [DocLexer::T_NONE],
            ]
        );

        try {
            $sequence->current();

            $this->fail('Expected exception to be thrown.');
        } catch (UnexpectedTokenException $exception) {
            $this->assertSame(
                'Token #0 (1) is not used by this library.',
                $exception->getMessage()
            );
        }
    }
}
