<?php
/**
 * Pest helper stubs for static analysis / IDEs.
 * These functions are intentionally minimal — they're not used at runtime in tests,
 * they only help static analyzers (Intelephense, PhpStorm) understand the global
 * Pest functions and avoid "undefined function" diagnostics.
 */

if (!function_exists('it')) {
    function it(string $description, callable $closure = null) {}
}

if (!function_exists('test')) {
    function test(string $description, callable $closure = null) {}
}

if (!function_exists('uses')) {
    function uses($traitOrClass) {}
}

if (!function_exists('beforeEach')) {
    function beforeEach(callable $closure) {}
}

if (!function_exists('afterEach')) {
    function afterEach(callable $closure) {}
}

if (!function_exists('beforeAll')) {
    function beforeAll(callable $closure) {}
}

if (!function_exists('afterAll')) {
    function afterAll(callable $closure) {}
}

if (!function_exists('expect')) {
    /**
     * Return a very small expectation helper for static analysis. It intentionally returns
     * an object with common expectation methods used in tests.
     */
    function expect($value = null)
    {
        return new class($value) {
            public function __construct(protected $value) {}
            public function __call($name, $args) { return $this; }
        };
    }
}
