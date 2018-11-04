<?php

namespace KevinGH\Box\Annotation\Convert;

use Doctrine\Common\Annotations\DocLexer;
use KevinGH\Box\Annotation\TestConvert;
use KevinGH\Box\Annotation\Tokens;
use Herrera\PHPUnit\TestCase;

class AbstractConvertTest extends TestCase
{
    public function testConvert()
    {
        $converter = new TestConvert();
        $tokens = new Tokens(
            array(
                array(DocLexer::T_AT),
                array(DocLexer::T_AT),
                array(DocLexer::T_AT),
                array(DocLexer::T_AT),
            )
        );

        $result = $converter->convert($tokens);

        $this->assertEquals(4, $result);
        $this->assertSame($tokens, $converter->tokens);
    }
}
