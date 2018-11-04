<?php

namespace KevinGH\Box\Annotation;

use Doctrine\Common\Annotations\DocLexer;
use KevinGH\Box\Annotation\Tokens;
use Herrera\PHPUnit\TestCase;

class TokensTest extends TestCase
{
    /**
     * @var Tokens
     */
    private $tokens;

    public function getTokenAndValue()
    {
        return array(
            array(array(DocLexer::T_FALSE, 'false'), false),
            array(array(DocLexer::T_FLOAT, '1.23'), 1.23),
            array(array(DocLexer::T_IDENTIFIER, 'CONSTANT'), 'CONSTANT'),
            array(array(DocLexer::T_INTEGER, '123'), 123),
            array(array(DocLexer::T_NULL, 'null'), null),
            array(array(DocLexer::T_STRING, 'test'), 'test'),
            array(array(DocLexer::T_TRUE, 'TRUE'), true),
        );
    }

    public function getTokenWithValue()
    {
        return array(
            array(DocLexer::T_FALSE),
            array(DocLexer::T_FLOAT),
            array(DocLexer::T_IDENTIFIER),
            array(DocLexer::T_INTEGER),
            array(DocLexer::T_NULL),
            array(DocLexer::T_STRING),
            array(DocLexer::T_TRUE),
        );
    }

    public function testConstruct()
    {
        $tokens = array(
            1 => array(DocLexer::T_AT),
            4 => array(DocLexer::T_IDENTIFIER, 'test')
        );

        $expected = array(
            0 => array(DocLexer::T_AT),
            1 => array(DocLexer::T_IDENTIFIER, 'test')
        );

        $this->assertEquals(
            $expected,
            $this->getPropertyValue(
                new Tokens($tokens),
                'tokens'
            )
        );
    }

    public function testCount()
    {
        $this->assertEquals(2, count($this->tokens));
    }

    public function testCurrent()
    {
        $this->assertEquals(
            array(DocLexer::T_AT),
            $this->tokens->current()
        );
    }

    public function testGetArray()
    {
        $this->assertEquals(
            array(
                array(DocLexer::T_AT),
                array(DocLexer::T_IDENTIFIER, 'test')
            ),
            $this->tokens->getArray()
        );
    }

    public function testGetKey()
    {
        $tokens = new Tokens(
            array(
                array(DocLexer::T_AT),
                array(DocLexer::T_IDENTIFIER, 'test'),
                array(DocLexer::T_OPEN_PARENTHESIS),
                array(DocLexer::T_OPEN_CURLY_BRACES),
                array(DocLexer::T_IDENTIFIER, 'a'),
                array(DocLexer::T_EQUALS),
                array(DocLexer::T_INTEGER, '123'),
                array(DocLexer::T_COMMA),
                array(DocLexer::T_IDENTIFIER, 'b'),
                array(DocLexer::T_COLON),
                array(DocLexer::T_INTEGER, '123'),
                array(DocLexer::T_COMMA),
                array(DocLexer::T_STRING, 'test'),
                array(DocLexer::T_CLOSE_CURLY_BRACES),
                array(DocLexer::T_CLOSE_PARENTHESIS),
            )
        );

        $this->assertNull($tokens->getKey());
        $this->assertEquals('a', $tokens->getKey(6));
        $this->assertEquals('b', $tokens->getKey(10));
        $this->assertNull($tokens->getKey(12));
    }

    /**
     * @dataProvider getTokenAndValue
     */
    public function testGetId($token)
    {
        $tokens = new Tokens(array($token));

        $this->assertSame($token[0], $tokens->getId(0));
    }

    public function testGetIdNone()
    {
        $tokens = new Tokens(array());

        $this->assertNull($tokens->getId());
    }

    public function testGetIdNoOffset()
    {
        $token = array(DocLexer::T_IDENTIFIER, 'test');
        $tokens = new Tokens(array($token));

        $this->assertEquals($token[0], $tokens->getId());
    }

    public function testGetToken()
    {
        $this->assertEquals(
            array(DocLexer::T_AT),
            $this->tokens->getToken(0)
        );
    }

    public function testGetTokenDefault()
    {
        $default = array(DocLexer::T_FALSE, '123');
        $tokens = new Tokens(array());

        $this->assertEquals($default, $tokens->getToken(0, $default));
    }

    public function testGetTokenInvalid()
    {
        $tokens = new Tokens(array(array('test')));

        $this->setExpectedException(
            'Herrera\\Annotations\\Exception\\InvalidTokenException',
            'Token #0 does not have a valid token identifier.'
        );

        $tokens->getToken(0);
    }

    public function testGetTokenMissingId()
    {
        $tokens = new Tokens(array(array()));

        $this->setExpectedException(
            'Herrera\\Annotations\\Exception\\InvalidTokenException',
            'Token #0 is missing its token identifier.'
        );

        $tokens->getToken(0);
    }

    /**
     * @dataProvider getTokenWithValue
     */
    public function testGetTokenMissingValue($token)
    {
        $tokens = new Tokens(array(array($token)));

        $this->setExpectedException(
            'Herrera\\Annotations\\Exception\\InvalidTokenException',
            "Token #0 ($token) is missing its value."
        );

        $tokens->current(0);
    }

    public function testGetTokenNoOffset()
    {
        $token = array(DocLexer::T_IDENTIFIER, 'test');
        $tokens = new Tokens(array($token));

        $this->assertEquals($token, $tokens->getToken());
    }

    /**
     * @dataProvider getTokenAndValue
     */
    public function testGetValue($token, $expected)
    {
        $tokens = new Tokens(array($token));

        $this->assertSame($expected, $tokens->getValue(0));
    }

    public function testGetValueNoOffset()
    {
        $tokens = new Tokens(
            array(
                array(DocLexer::T_IDENTIFIER, 'test')
            )
        );

        $this->assertEquals('test', $tokens->getValue());
    }

    public function testGetValueNotExpected()
    {
        $tokens = new Tokens(
            array(
                array(DocLexer::T_AT)
            )
        );

        $this->setExpectedException(
            'Herrera\\Annotations\\Exception\\LogicException',
            'Token #0 (101) is not expected to have a value.'
        );

        $tokens->getValue(0);
    }

    public function testKey()
    {
        $this->assertEquals(0, $this->tokens->key());
    }

    /**
     * @depends testKey
     */
    public function testNext()
    {
        $this->assertEquals(
            array(DocLexer::T_IDENTIFIER, 'test'),
            $this->tokens->next()
        );

        $this->assertEquals(1, $this->tokens->key());
    }

    public function testOffsetExists()
    {
        $this->assertTrue($this->tokens->offsetExists(1));
        $this->assertFalse($this->tokens->offsetExists(2));
    }

    public function testOffsetGet()
    {
        $this->assertEquals(
            array(DocLexer::T_IDENTIFIER, 'test'),
            $this->tokens->offsetGet(1)
        );
    }

    /**
     * @expectedException \KevinGH\Box\Annotation\Exception\OutOfRangeException
     * @expectedExceptionMessage  No value is set at offset 2.
     */
    public function testOffsetGetNotSet()
    {
        $this->tokens->offsetGet(2);
    }

    /**
     * @expectedException \KevinGH\Box\Annotation\Exception\LogicException
     * @expectedExceptionMessage New values cannot be added to the list of tokens.
     */
    public function testOffsetSet()
    {
        $this->tokens->offsetSet(1, 123);
    }

    /**
     * @expectedException \KevinGH\Box\Annotation\Exception\LogicException
     * @expectedExceptionMessage Existing tokens cannot be removed from the list of tokens.
     */
    public function testOffsetUnset()
    {
        $this->tokens->offsetUnset(1);
    }

    /**
     * @depends testKey
     * @depends testNext
     */
    public function testRewind()
    {
        $this->tokens->next();
        $this->tokens->rewind();

        $this->assertEquals(0, $this->tokens->key());
    }

    /**
     * @depends testNext
     */
    public function testValid()
    {
        $this->tokens->next();

        $this->assertTrue($this->tokens->valid());

        $this->tokens->next();

        $this->assertFalse($this->tokens->valid());
    }

    protected function setUp()
    {
        $this->tokens = new Tokens(
            array(
                array(DocLexer::T_AT),
                array(DocLexer::T_IDENTIFIER, 'test')
            )
        );
    }
}
