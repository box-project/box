<?php

namespace iter {
    /**
     * Converts a generator function into a rewindable generator function.
     *
     * This is implemented simply be remembering the arguments with which the
     * generator function is later called and just calling it again if a
     * rewind() occurs.
     *
     * Example:
     *
     *      $rewindableMap = iter\makeRewindable('iter\\map');
     *      $res = $rewindableMap(func\operator('*', 3), [1, 2, 3]);
     *      // $res is a rewindable iterator with elements [3, 6, 9]
     *
     * @param callable $function Generator function to make rewindable
     *
     * @return callable Rewindable generator function
     */
    function makeRewindable(callable $function) {
        return function(...$args) use ($function) {
            return new rewindable\_RewindableGenerator($function, $args);
        };
    }

    /**
     * Call a generator function, but make the result rewindable.
     *
     * This function does basically the same thing as makeRewindable(), but it
     * directly calls the function, rather than returning a lambda. Useful if
     * you want to do a one-off call, rather than using the rewindable function
     * multiple times.
     *
     * Example:
     *
     *      $res = iter\callRewindable('iter\\map', func\operator('*', 3), [1, 2, 3]);
     *      // $res is a rewindable iterator with elements [3, 6, 9]
     *
     * @param callable $function Generator function to call rewindably
     * @param mixed ...$args Function arguments
     *
     * @return \Iterator Rewindable generator result
     */
    function callRewindable(callable $function, ...$args) {
        return new rewindable\_RewindableGenerator($function, $args);
    }
}

namespace iter\rewindable {

    use ReturnTypeWillChange;

    /**
     * These functions are just rewindable wrappers around the normal
     * non-rewindable functions from the iter namespace
     */

    function range()         { return new _RewindableGenerator('iter\range',         func_get_args()); }
    function map()           { return new _RewindableGenerator('iter\map',           func_get_args()); }
    function mapKeys()       { return new _RewindableGenerator('iter\mapKeys',       func_get_args()); }
    function mapWithKeys()   { return new _RewindableGenerator('iter\mapWithKeys',   func_get_args()); }
    function flatMap()       { return new _RewindableGenerator('iter\flatMap',       func_get_args()); }
    function reindex()       { return new _RewindableGenerator('iter\reindex',       func_get_args()); }
    function filter()        { return new _RewindableGenerator('iter\filter',        func_get_args()); }
    function enumerate()     { return new _RewindableGenerator('iter\enumerate',     func_get_args()); }
    function toPairs()       { return new _RewindableGenerator('iter\toPairs',       func_get_args()); }
    function fromPairs()     { return new _RewindableGenerator('iter\fromPairs',     func_get_args()); }
    function reductions()    { return new _RewindableGenerator('iter\reductions',    func_get_args()); }
    function zip()           { return new _RewindableGenerator('iter\zip',           func_get_args()); }
    function zipKeyValue()   { return new _RewindableGenerator('iter\zipKeyValue',   func_get_args()); }
    function chain()         { return new _RewindableGenerator('iter\chain',         func_get_args()); }
    function product()       { return new _RewindableGenerator('iter\product',       func_get_args()); }
    function slice()         { return new _RewindableGenerator('iter\slice',         func_get_args()); }
    function take()          { return new _RewindableGenerator('iter\take',          func_get_args()); }
    function drop()          { return new _RewindableGenerator('iter\drop',          func_get_args()); }
    function repeat()        { return new _RewindableGenerator('iter\repeat',        func_get_args()); }
    function takeWhile()     { return new _RewindableGenerator('iter\takeWhile',     func_get_args()); }
    function dropWhile()     { return new _RewindableGenerator('iter\dropWhile',     func_get_args()); }
    function keys()          { return new _RewindableGenerator('iter\keys',          func_get_args()); }
    function values()        { return new _RewindableGenerator('iter\values',        func_get_args()); }
    function flatten()       { return new _RewindableGenerator('iter\flatten',       func_get_args()); }
    function flip()          { return new _RewindableGenerator('iter\flip',          func_get_args()); }
    function chunk()         { return new _RewindableGenerator('iter\chunk',         func_get_args()); }
    function chunkWithKeys() { return new _RewindableGenerator('iter\chunkWithKeys', func_get_args()); }

    /**
     * This class is used for the internal implementation of rewindable
     * generators. Should not be used directly, instead use makeRewindable() or
     * callRewindable().
     *
     * @internal
     */
    class _RewindableGenerator implements \Iterator {
        protected $function;
        protected $args;
        /** @var \Generator */
        protected $generator;

        public function __construct(callable $function, array $args) {
            $this->function = $function;
            $this->args = $args;
            $this->generator = null;
        }

        public function rewind(): void {
            $function = $this->function;
            $this->generator = $function(...$this->args);
        }

        public function next(): void {
            if (!$this->generator) { $this->rewind(); }
            $this->generator->next();
        }

        public function valid(): bool {
            if (!$this->generator) { $this->rewind(); }
            return $this->generator->valid();
        }

        #[ReturnTypeWillChange]
        public function key() {
            if (!$this->generator) { $this->rewind(); }
            return $this->generator->key();
        }

        #[ReturnTypeWillChange]
        public function current() {
            if (!$this->generator) { $this->rewind(); }
            return $this->generator->current();
        }

        public function send($value = null) {
            if (!$this->generator) { $this->rewind(); }
            return $this->generator->send($value);
        }

        public function throw($exception) {
            if (!$this->generator) { $this->rewind(); }
            return $this->generator->throw($exception);
        }
    }
}
