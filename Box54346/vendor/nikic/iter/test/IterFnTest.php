<?php

namespace iter;

use iter\func;
use PHPUnit\Framework\TestCase;

class IterFnTest extends TestCase {
    public function testIndex() {
        $getIndex3 = func\index(3);
        $getIndexTest = func\index('test');

        $arr1 = [10, 11, 12, 13, 14, 15];
        $arr2 = ['foo' => 'bar', 'test' => 'tset', 'bar' => 'foo'];

        $this->assertSame($arr1[3], $getIndex3($arr1));
        $this->assertSame($arr2['test'], $getIndexTest($arr2));
    }

    public function testNestedIndex() {
        $getIndexFooBar = func\nested_index('foo', 'bar');
        $getIndexFooBarBaz = func\nested_index('foo', 'bar', 'baz');
        $getEmptyIndex = func\nested_index();

        $array = [
            'foo' => [
                'bar' => [
                    'baz' => 42
                ]
            ]
        ];

        $this->assertSame($array['foo']['bar'], $getIndexFooBar($array));
        $this->assertSame($array['foo']['bar']['baz'], $getIndexFooBarBaz($array));
        $this->assertSame($array, $getEmptyIndex($array));
    }

    public function testProperty() {
        $getPropertyFoo = func\property('foo');
        $getPropertyBar = func\property('bar');

        $obj = (object) ['foo' => 'bar', 'bar' => 'foo'];

        $this->assertSame($obj->foo, $getPropertyFoo($obj));
        $this->assertSame($obj->bar, $getPropertyBar($obj));
    }

    public function testMethod() {
        $callMethod1 = func\method('test');
        $callMethod2 = func\method('test', []);
        $callMethod3 = func\method('test', ['a', 'b']);

        $obj = new _MethodTestDummy;

        $this->assertSame([], $callMethod1($obj));
        $this->assertSame([], $callMethod2($obj));
        $this->assertSame(['a', 'b'], $callMethod3($obj));
    }

    public function testNot() {
        $constFalse = func\not(function() { return true; });
        $constTrue = func\not(function() { return false; });
        $invert = func\not(function($bool) { return $bool; });
        $nand = func\not(func\operator('&&'));

        $this->assertFalse($constFalse());
        $this->assertTrue($constTrue());
        $this->assertFalse($invert(true));
        $this->assertTrue($invert(false));
        $this->assertFalse($nand(true, true));
        $this->assertTrue($nand(true, false));
    }

    /** @dataProvider provideTestOperator */
    public function testOperator($op, $a, $b, $result) {
        $fn1 = func\operator($op);
        $fn2 = func\operator($op, $b);

        $this->assertSame($result, $fn1($a, $b));
        $this->assertSame($result, $fn2($a));
    }

    public function provideTestOperator() {
        return [
            'instanceof'                 => ['instanceof', new \stdClass, 'stdClass', true],
            '*'                          => ['*', 3, 2, 6],
            '/'                          => ['/', 3, 2, 1.5],
            '%'                          => ['%', 3, 2, 1],
            '+'                          => ['+', 3, 2, 5],
            '-'                          => ['-', 3, 2, 1],
            '.'                          => ['.', 'foo', 'bar', 'foobar'],
            '<<'                         => ['<<', 1, 8, 256],
            '>>'                         => ['>>', 256, 8, 1],
            '<'                          => ['<', 3, 5, true],
            '<='                         => ['<=', 5, 5, true],
            '>'                          => ['>', 3, 5, false],
            '>='                         => ['>=', 3, 5, false],
            '== with number and string'  => ['==', 0, 'foo', (PHP_VERSION_ID > 80000 ? false : true)],
            '== with numbers'            => ['==', 0, '0', true],
            '!='                         => ['!=', 1, 'foo', true],
            '==='                        => ['===', 0, 'foo', false],
            '!=='                        => ['!==', 0, 'foo', true],
            '&'                          => ['&', 3, 1, 1],
            '|'                          => ['|', 3, 1, 3],
            '^'                          => ['^', 3, 1, 2],
            '&&'                         => ['&&', true, false, false],
            '||'                         => ['||', true, false, true],
            '**'                         => ['**', 2, 4, 16],
            '<=> with arrays'            => ['<=>', [0=>1,1=>0], [1=>0,0=>1], 0],
            '<=> with scientific floats' => ['<=>', '2e1', '1e10', -1],
            '<=> with stdClass/SplStack' => ['<=>', new \stdClass(), new \SplStack(), 1],
            '<=> with SplStack/stdClass' => ['<=>', new \SplStack(), new \stdClass(), 1],
        ];
    }

    
    public function testInvalidOperator() {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown operator "***"');

        func\operator('***');
    }
}

class _MethodTestDummy {
    public function test(...$args) {
        return $args;
    }
}
