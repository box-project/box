<?php

namespace iter;

use iter\rewindable;
use PHPUnit\Framework\TestCase;

class IterRewindableTest extends TestCase {
    private function assertRewindableEquals($array, $iter, $withKeys = false) {
        $fn = $withKeys ? 'iter\\toArrayWithKeys' : 'iter\\toArray';
        $this->assertSame($array, $fn($iter));
        $this->assertSame($array, $fn($iter));
    }

    public function testRewindableVariants() {
        $this->assertRewindableEquals(
            [1, 2, 3, 4, 5],
            rewindable\range(1, 5)
        );
        $this->assertRewindableEquals(
            [3, 6, 9, 12, 15],
            rewindable\map(func\operator('*', 3), rewindable\range(1, 5))
        );
        $this->assertRewindableEquals(
            ['a' => 1, 'b' => 2, 'c' => 3],
            rewindable\mapKeys('strtolower', ['A' => 1, 'B' => 2, 'C' => 3]),
            true
        );
        $this->assertRewindableEquals(
            [0, 1, 4, 9, 16, 25],
            rewindable\mapWithKeys(func\operator('*'), rewindable\range(0, 5))
        );
        $this->assertRewindableEquals(
            [-1, 1, -2, 2, -3, 3, -4, 4, -5, 5],
            rewindable\flatMap(function($v) {
                return [-$v, $v];
            }, [1, 2, 3, 4, 5])
        );
        $this->assertRewindableEquals(
            [2 => 1, 4 => 2, 6 => 3, 8 => 4],
            rewindable\reindex(func\operator('*', 2), [1, 2, 3, 4]),
            true
        );
        $this->assertRewindableEquals(
            [-5, -4, -3, -2, -1],
            rewindable\filter(func\operator('<', 0), rewindable\range(-5, 5))
        );
        $this->assertRewindableEquals(
            [[0,0], [1,1], [2,2], [3,3], [4,4], [5,5]],
            rewindable\enumerate(rewindable\range(0, 5))
        );
        $this->assertRewindableEquals(
            [[0,0], [1,1], [2,2], [3,3], [4,4], [5,5]],
            rewindable\toPairs(rewindable\range(0, 5))
        );
        $this->assertRewindableEquals(
            [0, 1, 2, 3, 4, 5],
            rewindable\fromPairs([[0,0], [1,1], [2,2], [3,3], [4,4], [5,5]])
        );
        $this->assertRewindableEquals(
            [[0,5], [1,4], [2,3], [3,2], [4,1], [5,0]],
            rewindable\zip(rewindable\range(0, 5), rewindable\range(5, 0, -1))
        );
        $this->assertRewindableEquals(
            [1, 3, 6, 10, 15],
            rewindable\reductions(func\operator('+'), rewindable\range(1, 5), 0)
        );
        $this->assertRewindableEquals(
            [5=>0, 4=>1, 3=>2, 2=>3, 1=>4, 0=>5],
            rewindable\zipKeyValue(rewindable\range(5, 0, -1), rewindable\range(0, 5)),
            true
        );
        $this->assertRewindableEquals(
            [1, 2, 3, 4, 5, 6, 7, 8, 9],
            rewindable\chain(rewindable\range(1, 3), rewindable\range(4, 6), rewindable\range(7, 9))
        );
        $this->assertRewindableEquals(
            [5, 6, 7, 8, 9],
            rewindable\slice(rewindable\range(0, 9), 5)
        );
        $this->assertRewindableEquals(
            [1, 2, 3],
            rewindable\take(3, [1, 2, 3, 4, 5])
        );
        $this->assertRewindableEquals(
            [4, 5],
            rewindable\drop(3, [1, 2, 3, 4, 5])
        );
        $this->assertRewindableEquals(
            [1, 1, 1, 1, 1],
            rewindable\repeat(1, 5)
        );
        $this->assertRewindableEquals(
            ['b', 'd', 'f'],
            rewindable\values(['a' => 'b', 'c' => 'd', 'e' => 'f']),
            true
        );
        $this->assertRewindableEquals(
            ['a', 'c', 'e'],
            rewindable\keys(['a' => 'b', 'c' => 'd', 'e' => 'f']),
            true
        );
        $this->assertRewindableEquals(
            [3, 1, 4],
            rewindable\takeWhile(func\operator('>', 0), [3, 1, 4, -1, 5])
        );
        $this->assertRewindableEquals(
            [-1, 5],
            rewindable\dropWhile(func\operator('>', 0), [3, 1, 4, -1, 5])
        );
        $this->assertRewindableEquals(
            [1, 2, 3, 4, 5],
            rewindable\flatten([[1, [[2, [[]], 3], 4]], 5])
        );
        $this->assertRewindableEquals(
            [1 => 'a', 2 => 'b', 3 => 'c'],
            rewindable\flip(['a' => 1, 'b' => 2, 'c' => 3]),
            true
        );
        $this->assertRewindableEquals(
            [[1, 2], [3, 4], [5]],
            rewindable\chunk([1, 2, 3, 4, 5], 2)
        );
        $this->assertRewindableEquals(
            [['a' => 1, 'b' => 2], ['c' => 3, 'd' => 4], ['e' => 5]],
            rewindable\chunkWithKeys(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5], 2),
            true
        );
        $this->assertRewindableEquals(
            [[1,3,5],[1,3,6],[1,4,5],[1,4,6],[2,3,5],[2,3,6],[2,4,5],[2,4,6]],
            rewindable\product([1,2], [3,4], [5,6])
        );
    }

    public function testMakeRewindable() {
        $range = makeRewindable('iter\\range');
        $map = makeRewindable('iter\\map');
        $this->assertRewindableEquals(
            [3, 6, 9, 12, 15],
            $map(func\operator('*', 3), $range(1, 5))
        );
    }

    public function testCallRewindable() {
        $this->assertRewindableEquals(
            [3, 6, 9, 12, 15],
            callRewindable(
                'iter\\map',
                func\operator('*', 3), callRewindable('iter\\range', 1, 5)
            )
        );
    }

    public function testRewindableGenerator() {
        // Make sure that send() and throw() work with rewindable generator
        $genFn = makeRewindable(function() {
            $startValue = yield;
            try {
                for (;;) yield $startValue++;
            } catch (\Exception $e) {
                yield 'end';
            }
        });

        /** @var rewindable\_RewindableGenerator $gen */
        $gen = $genFn();

        for ($i = 0; $i < 2; ++$i) {
            $gen->rewind();
            $gen->send(10);
            $this->assertEquals(10, $gen->current());
            $gen->next();
            $this->assertEquals(11, $gen->current());
            $gen->next();
            $this->assertEquals(12, $gen->current());
            $gen->throw(new \Exception);
            $this->assertEquals('end', $gen->current());
        }
    }
}
