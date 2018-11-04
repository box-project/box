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
use KevinGH\Box\Annotation\Exception\InvalidTokenException;
use KevinGH\Box\Annotation\Exception\LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

/**
 * @covers \KevinGH\Box\Annotation\Tokens
 */
class TokensTest extends TestCase
{
    /**
     * @var Tokens
     */
    private $tokens;

    public function getTokenAndValue(): array
    {
        return [
            [[DocLexer::T_FALSE, 'false'], false],
            [[DocLexer::T_FLOAT, '1.23'], 1.23],
            [[DocLexer::T_IDENTIFIER, 'CONSTANT'], 'CONSTANT'],
            [[DocLexer::T_INTEGER, '123'], 123],
            [[DocLexer::T_NULL, 'null'], null],
            [[DocLexer::T_STRING, 'test'], 'test'],
            [[DocLexer::T_TRUE, 'TRUE'], true],
        ];
    }

    public function getTokenWithValue(): array
    {
        return [
            [DocLexer::T_FALSE],
            [DocLexer::T_FLOAT],
            [DocLexer::T_IDENTIFIER],
            [DocLexer::T_INTEGER],
            [DocLexer::T_NULL],
            [DocLexer::T_STRING],
            [DocLexer::T_TRUE],
        ];
    }

    public function testConstruct(): void
    {
        $tokens = [
            1 => [DocLexer::T_AT],
            4 => [DocLexer::T_IDENTIFIER, 'test'],
        ];

        $expected = [
            0 => [DocLexer::T_AT],
            1 => [DocLexer::T_IDENTIFIER, 'test'],
        ];

        $this->assertEquals(
            $expected,
            $this->getPropertyValue(
                new Tokens($tokens),
                'tokens'
            )
        );
    }

    public function testCount(): void
    {
        $this->assertEquals(2, count($this->tokens));
    }

    public function testCurrent(): void
    {
        $this->assertEquals(
            [DocLexer::T_AT],
            $this->tokens->current()
        );
    }

    public function testGetArray(): void
    {
        $this->assertEquals(
            [
                [DocLexer::T_AT],
                [DocLexer::T_IDENTIFIER, 'test'],
            ],
            $this->tokens->getArray()
        );
    }

    public function testGetKey(): void
    {
        $tokens = new Tokens(
            [
                [DocLexer::T_AT],
                [DocLexer::T_IDENTIFIER, 'test'],
                [DocLexer::T_OPEN_PARENTHESIS],
                [DocLexer::T_OPEN_CURLY_BRACES],
                [DocLexer::T_IDENTIFIER, 'a'],
                [DocLexer::T_EQUALS],
                [DocLexer::T_INTEGER, '123'],
                [DocLexer::T_COMMA],
                [DocLexer::T_IDENTIFIER, 'b'],
                [DocLexer::T_COLON],
                [DocLexer::T_INTEGER, '123'],
                [DocLexer::T_COMMA],
                [DocLexer::T_STRING, 'test'],
                [DocLexer::T_CLOSE_CURLY_BRACES],
                [DocLexer::T_CLOSE_PARENTHESIS],
            ]
        );

        $this->assertNull($tokens->getKey());
        $this->assertEquals('a', $tokens->getKey(6));
        $this->assertEquals('b', $tokens->getKey(10));
        $this->assertNull($tokens->getKey(12));
    }

    /**
     * @dataProvider getTokenAndValue
     *
     * @param mixed $token
     */
    public function testGetId($token): void
    {
        $tokens = new Tokens([$token]);

        $this->assertSame($token[0], $tokens->getId(0));
    }

    public function testGetIdNone(): void
    {
        $tokens = new Tokens([]);

        $this->assertNull($tokens->getId());
    }

    public function testGetIdNoOffset(): void
    {
        $token = [DocLexer::T_IDENTIFIER, 'test'];
        $tokens = new Tokens([$token]);

        $this->assertEquals($token[0], $tokens->getId());
    }

    public function testGetToken(): void
    {
        $this->assertEquals(
            [DocLexer::T_AT],
            $this->tokens->getToken(0)
        );
    }

    public function testGetTokenDefault(): void
    {
        $default = [DocLexer::T_FALSE, '123'];
        $tokens = new Tokens([]);

        $this->assertEquals($default, $tokens->getToken(0, $default));
    }

    public function testGetTokenInvalid(): void
    {
        $tokens = new Tokens([['test']]);

        try {
            $tokens->getToken(0);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidTokenException $exception) {
            $this->assertSame(
                'Token #0 does not have a valid token identifier.',
                $exception->getMessage()
            );
        }
    }

    public function testGetTokenMissingId(): void
    {
        $tokens = new Tokens([[]]);

        try {
            $tokens->getToken(0);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidTokenException $exception) {
            $this->assertSame(
                'Token #0 is missing its token identifier.',
                $exception->getMessage()
            );
        }
    }

    /**
     * @dataProvider getTokenWithValue
     *
     * @param mixed $token
     */
    public function testGetTokenMissingValue($token): void
    {
        $tokens = new Tokens([[$token]]);

        try {
            $tokens->current(0);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidTokenException $exception) {
            $this->assertSame(
                "Token #0 ($token) is missing its value.",
                $exception->getMessage()
            );
        }
    }

    public function testGetTokenNoOffset(): void
    {
        $token = [DocLexer::T_IDENTIFIER, 'test'];
        $tokens = new Tokens([$token]);

        $this->assertEquals($token, $tokens->getToken());
    }

    /**
     * @dataProvider getTokenAndValue
     *
     * @param mixed $token
     * @param mixed $expected
     */
    public function testGetValue($token, $expected): void
    {
        $tokens = new Tokens([$token]);

        $this->assertSame($expected, $tokens->getValue(0));
    }

    public function testGetValueNoOffset(): void
    {
        $tokens = new Tokens(
            [
                [DocLexer::T_IDENTIFIER, 'test'],
            ]
        );

        $this->assertEquals('test', $tokens->getValue());
    }

    public function testGetValueNotExpected(): void
    {
        $tokens = new Tokens(
            [
                [DocLexer::T_AT],
            ]
        );

        try {
            $tokens->getValue(0);

            $this->fail('Expected exception to be thrown.');
        } catch (LogicException $exception) {
            $this->assertSame(
                'Token #0 (101) is not expected to have a value.',
                $exception->getMessage()
            );
        }
    }

    public function testKey(): void
    {
        $this->assertEquals(0, $this->tokens->key());
    }

    /**
     * @depends testKey
     */
    public function testNext(): void
    {
        $this->assertEquals(
            [DocLexer::T_IDENTIFIER, 'test'],
            $this->tokens->next()
        );

        $this->assertEquals(1, $this->tokens->key());
    }

    public function testOffsetExists(): void
    {
        $this->assertTrue($this->tokens->offsetExists(1));
        $this->assertFalse($this->tokens->offsetExists(2));
    }

    public function testOffsetGet(): void
    {
        $this->assertEquals(
            [DocLexer::T_IDENTIFIER, 'test'],
            $this->tokens->offsetGet(1)
        );
    }

    /**
     * @expectedException \KevinGH\Box\Annotation\Exception\OutOfRangeException
     * @expectedExceptionMessage  No value is set at offset 2.
     */
    public function testOffsetGetNotSet(): void
    {
        $this->tokens->offsetGet(2);
    }

    /**
     * @expectedException \KevinGH\Box\Annotation\Exception\LogicException
     * @expectedExceptionMessage New values cannot be added to the list of tokens.
     */
    public function testOffsetSet(): void
    {
        $this->tokens->offsetSet(1, 123);
    }

    /**
     * @expectedException \KevinGH\Box\Annotation\Exception\LogicException
     * @expectedExceptionMessage Existing tokens cannot be removed from the list of tokens.
     */
    public function testOffsetUnset(): void
    {
        $this->tokens->offsetUnset(1);
    }

    /**
     * @depends testKey
     * @depends testNext
     */
    public function testRewind(): void
    {
        $this->tokens->next();
        $this->tokens->rewind();

        $this->assertEquals(0, $this->tokens->key());
    }

    /**
     * @depends testNext
     */
    public function testValid(): void
    {
        $this->tokens->next();

        $this->assertTrue($this->tokens->valid());

        $this->tokens->next();

        $this->assertFalse($this->tokens->valid());
    }

    protected function setUp(): void
    {
        $this->tokens = new Tokens(
            [
                [DocLexer::T_AT],
                [DocLexer::T_IDENTIFIER, 'test'],
            ]
        );
    }

    /**
     * @param mixed $object
     *
     * @return mixed The property value
     */
    final protected function getPropertyValue($object, string $property)
    {
        $reflectionObject = new ReflectionObject($object);

        while (false === $reflectionObject->hasProperty($property) && false !== $reflectionObject->getParentClass()) {
            $reflectionObject = $reflectionObject->getParentClass();
        }

        $propertyReflection = $reflectionObject->getProperty($property);
        $propertyReflection->setAccessible(true);

        return $propertyReflection->getValue($object);
    }

    final protected function setPropertyValue($object, string $property, $value): void
    {
        $reflectionObject = new ReflectionObject($object);

        while (false === $reflectionObject->hasProperty($property) && false !== $reflectionObject->getParentClass()) {
            $reflectionObject = $reflectionObject->getParentClass();
        }

        $propertyReflection = $reflectionObject->getProperty($property);
        $propertyReflection->setAccessible(true);

        $propertyReflection->setValue($object, $value);
    }
}
