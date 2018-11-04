<?php

namespace KevinGH\Box\Annotation;

use Doctrine\Common\Annotations\DocLexer;
use KevinGH\Box\Annotation\Exception\InvalidArgumentException;
use KevinGH\Box\Annotation\Exception\UnexpectedTokenException;
use KevinGH\Box\Annotation\Sequence;
use KevinGH\Box\Annotation\TestTokens;
use KevinGH\Box\Annotation\Tokens;

class SequenceTest extends TestTokens
{
    public function getTokens()
    {
        $tokens = parent::getTokens();

        foreach ($tokens as $i => $list) {
            $tokens[$i] = array($list);
        }

        return $tokens;
    }

    public function testConstruct()
    {
        $tokens = array(
            array(123)
        );

        $sequence = new Sequence($tokens);

        $this->assertEquals(
            $tokens,
            $this->getPropertyValue($sequence, 'tokens')
        );
    }

    public function testConstructWithTokens()
    {
        $tokens = array(
            array(123)
        );

        $sequence = new Sequence(
            new Tokens($tokens)
        );

        $this->assertEquals(
            $tokens,
            $this->getPropertyValue($sequence, 'tokens')
        );
    }

    public function testConstructInvalid()
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
     */
    public function testCurrent($tokens)
    {
        $sequence = new Sequence($tokens);

        foreach ($tokens as $list) {
            $this->assertEquals($list, $sequence->current());

            $sequence->next();
        }
    }

    public function testCurrentUnexpected()
    {
        $sequence = new Sequence(
            array(
                array(DocLexer::T_IDENTIFIER, 'test')
            )
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

    public function testCurrentUnexpectedDeeper()
    {
        $sequence = new Sequence(
            array(
                array(DocLexer::T_IDENTIFIER, 'test'),
                array(DocLexer::T_OPEN_PARENTHESIS),
            )
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

    public function testCurrentUnused()
    {
        $sequence = new Sequence(
            array(
                array(DocLexer::T_NONE)
            )
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
