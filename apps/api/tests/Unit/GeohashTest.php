<?php

use App\Support\Geohash;

test('encode matches known vectors', function () {
    expect(Geohash::encode(57.64911, 10.40744, 11))->toBe('u4pruydqqvj')
        ->and(Geohash::encode(42.605, -5.603, 5))->toBe('ezs42')
        ->and(Geohash::encode(57.64911, 10.40744))->toBe('u4pruydqq'); // default precision 9
});

test('encode rejects out-of-range coordinates and precision', function () {
    expect(fn () => Geohash::encode(91, 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Geohash::encode(0, 181))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Geohash::encode(0, 0, 0))->toThrow(InvalidArgumentException::class);
});

test('decode returns the approximate center of the cell', function () {
    $point = Geohash::decode('u4pruydqqvj');

    expect($point['lat'])->toEqualWithDelta(57.64911, 0.0001)
        ->and($point['lng'])->toEqualWithDelta(10.40744, 0.0001);

    $roundTrip = Geohash::decode(Geohash::encode(-33.9249, 18.4241, 9));

    expect($roundTrip['lat'])->toEqualWithDelta(-33.9249, 0.001)
        ->and($roundTrip['lng'])->toEqualWithDelta(18.4241, 0.001);
});

test('neighbors match the known vector for dqcjq', function () {
    expect(Geohash::neighbors('dqcjq'))->toBe([
        'n' => 'dqcjw',
        'ne' => 'dqcjx',
        'e' => 'dqcjr',
        'se' => 'dqcjp',
        's' => 'dqcjn',
        'sw' => 'dqcjj',
        'w' => 'dqcjm',
        'nw' => 'dqcjt',
    ]);
});

test('neighbors handles border cells by recursing into the parent', function () {
    // u000 sits on the south-west corner of the "u" cell.
    $neighbors = Geohash::neighbors('u000');

    expect($neighbors)->toHaveCount(8)
        ->and(array_unique($neighbors))->toHaveCount(8)
        ->and($neighbors['s'])->toBe('spbp')
        ->and($neighbors['w'])->toBe('gbpb');
});

test('coverRadius picks a precision suited to the radius', function (float $radius, int $precision) {
    $prefixes = Geohash::coverRadius(-33.9249, 18.4241, $radius);

    expect($prefixes)->toHaveCount(9)
        ->and(array_unique(array_map(strlen(...), $prefixes)))->toBe([$precision])
        ->and($prefixes[0])->toBe(Geohash::encode(-33.9249, 18.4241, $precision));
})->with([
    [5.0, 5],
    [20.0, 4],
    [25.0, 3],
    [80.0, 3],
    [100.0, 2],
]);
