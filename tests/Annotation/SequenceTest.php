<?php

namespace KevinGH\Box\Annotation;

use Doctrine\Common\Annotations\DocLexer;
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
        $this->setExpectedException(
            'Herrera\\Annotations\\Exception\\InvalidArgumentException',
            'The $tokens argument must be an array or instance of Tokens.'
        );

        new Sequence(123);
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

        $this->setExpectedException(
            'Herrera\\Annotations\\Exception\\UnexpectedTokenException',
            'Token #0 (100) is not expected here.'
        );

        $sequence->current();
    }

    public function testCurrentUnexpectedDeeper()
    {
        $sequence = new Sequence(
            array(
                array(DocLexer::T_IDENTIFIER, 'test'),
                array(DocLexer::T_OPEN_PARENTHESIS),
            )
        );

        $this->setExpectedException(
            'Herrera\\Annotations\\Exception\\UnexpectedTokenException',
            'Token #1 (109) is not expected here.'
        );

        $sequence->next();
        $sequence->current();
    }

    public function testCurrentUnused()
    {
        $sequence = new Sequence(
            array(
                array(DocLexer::T_NONE)
            )
        );

        $this->setExpectedException(
            'Herrera\\Annotations\\Exception\\UnexpectedTokenException',
            'Token #0 (1) is not used by this library.'
        );

        $sequence->current();
    }
}
