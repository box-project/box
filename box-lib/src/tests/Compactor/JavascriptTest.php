<?php

namespace Herrera\Box\Tests\Compactor;

use Herrera\PHPUnit\TestCase;
use Herrera\Box\Compactor\Javascript;


class JavascriptTest extends TestCase
{

    public function testSupports()
    {
        $compactor = new Javascript();
        $this->assertTrue($compactor->supports('test.js'));
        $this->assertFalse($compactor->supports('test'));
        $this->assertFalse($compactor->supports('test.min.js'));
    }

    /**
     * @dataProvider javascriptProvider
     */
    public function testCompact($input, $output)
    {
        $compactor = new Javascript();
        $this->assertEquals($compactor->compact($input), $output);
    }

    public function javascriptProvider()
    {
        return array(

            array('new Array();', 'new Array();'),

            array('(function(){
        var Array = function(){};
        return new Array(1, 2, 3, 4);
})();', '(function(){var Array=function(){};return new Array(1,2,3,4);})();')


        );
    }
}
