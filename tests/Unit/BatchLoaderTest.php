<?php

use App\Base\GraphQL\BatchLoader;
use GraphQL\Deferred;

/*
| A garantia contra N+1: N `load()` antes de resolver viram **uma** chamada a `$fetch`.
*/

it('answers every key loaded before resolving with a single fetch', function () {
    $calls = [];
    $loader = new BatchLoader(function (array $keys) use (&$calls) {
        $calls[] = $keys;

        return array_combine($keys, array_map(fn(string $key) => "value-{$key}", $keys));
    });

    $deferred = [$loader->load('a'), $loader->load('b'), $loader->load(3), $loader->load('a')];
    Deferred::runQueue();

    expect($calls)->toBe([['a', 'b', '3']])
        ->and(array_map(fn(Deferred $value) => $value->result, $deferred))->toBe(['value-a', 'value-b', 'value-3', 'value-a']);
});

it('fetches keys requested after the first batch in a second batch, and returns the default for missing keys', function () {
    $calls = [];
    $loader = new BatchLoader(function (array $keys) use (&$calls) {
        $calls[] = $keys;

        return array_key_exists('x', array_flip($keys)) ? ['x' => ['found']] : [];
    });

    $first = $loader->load('x', []);
    Deferred::runQueue();
    $second = $loader->load('y', []);
    $again = $loader->load('x', []);
    Deferred::runQueue();

    expect($calls)->toBe([['x'], ['y']])
        ->and($first->result)->toBe(['found'])
        ->and($second->result)->toBe([])
        ->and($again->result)->toBe(['found']);
});
