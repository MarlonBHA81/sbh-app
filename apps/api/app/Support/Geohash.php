<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Pure-PHP geohash encoder/decoder with neighbor lookup, used for the
 * nearby feed's prefix scan. Standard base-32 geohash alphabet.
 */
class Geohash
{
    private const BASE32 = '0123456789bcdefghjkmnpqrstuvwxyz';

    private const NEIGHBOR = [
        'n' => ['even' => 'p0r21436x8zb9dcf5h7kjnmqesgutwvy', 'odd' => 'bc01fg45238967deuvhjyznpkmstqrwx'],
        's' => ['even' => '14365h7k9dcfesgujnmqp0r2twvyx8zb', 'odd' => '238967debc01fg45kmstqrwxuvhjyznp'],
        'e' => ['even' => 'bc01fg45238967deuvhjyznpkmstqrwx', 'odd' => 'p0r21436x8zb9dcf5h7kjnmqesgutwvy'],
        'w' => ['even' => '238967debc01fg45kmstqrwxuvhjyznp', 'odd' => '14365h7k9dcfesgujnmqp0r2twvyx8zb'],
    ];

    private const BORDER = [
        'n' => ['even' => 'prxz', 'odd' => 'bcfguvyz'],
        's' => ['even' => '028b', 'odd' => '0145hjnp'],
        'e' => ['even' => 'bcfguvyz', 'odd' => 'prxz'],
        'w' => ['even' => '0145hjnp', 'odd' => '028b'],
    ];

    public static function encode(float $lat, float $lng, int $precision = 9): string
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new InvalidArgumentException('Latitude/longitude out of range.');
        }

        if ($precision < 1 || $precision > 12) {
            throw new InvalidArgumentException('Precision must be between 1 and 12.');
        }

        $latRange = [-90.0, 90.0];
        $lngRange = [-180.0, 180.0];

        $hash = '';
        $bit = 0;
        $index = 0;
        $even = true;

        while (strlen($hash) < $precision) {
            if ($even) {
                $mid = ($lngRange[0] + $lngRange[1]) / 2;

                if ($lng >= $mid) {
                    $index = ($index << 1) | 1;
                    $lngRange[0] = $mid;
                } else {
                    $index <<= 1;
                    $lngRange[1] = $mid;
                }
            } else {
                $mid = ($latRange[0] + $latRange[1]) / 2;

                if ($lat >= $mid) {
                    $index = ($index << 1) | 1;
                    $latRange[0] = $mid;
                } else {
                    $index <<= 1;
                    $latRange[1] = $mid;
                }
            }

            $even = ! $even;

            if (++$bit === 5) {
                $hash .= self::BASE32[$index];
                $bit = 0;
                $index = 0;
            }
        }

        return $hash;
    }

    /**
     * Decode a geohash to the center point of its cell.
     *
     * @return array{lat: float, lng: float}
     */
    public static function decode(string $hash): array
    {
        [$latRange, $lngRange] = self::bounds($hash);

        return [
            'lat' => ($latRange[0] + $latRange[1]) / 2,
            'lng' => ($lngRange[0] + $lngRange[1]) / 2,
        ];
    }

    /**
     * The adjacent cell in a cardinal direction (n, s, e, w).
     */
    public static function adjacent(string $hash, string $direction): string
    {
        $hash = strtolower($hash);

        if ($hash === '') {
            throw new InvalidArgumentException('Cannot compute the neighbor of an empty geohash.');
        }

        if (! isset(self::NEIGHBOR[$direction])) {
            throw new InvalidArgumentException("Invalid direction [{$direction}].");
        }

        $last = substr($hash, -1);
        $parent = substr($hash, 0, -1);
        $type = strlen($hash) % 2 === 0 ? 'even' : 'odd';

        if ($parent !== '' && str_contains(self::BORDER[$direction][$type], $last)) {
            $parent = self::adjacent($parent, $direction);
        }

        $position = strpos(self::NEIGHBOR[$direction][$type], $last);

        if ($position === false) {
            throw new InvalidArgumentException("Invalid geohash [{$hash}].");
        }

        return $parent.self::BASE32[$position];
    }

    /**
     * The eight neighboring cells of a geohash.
     *
     * @return array{n: string, ne: string, e: string, se: string, s: string, sw: string, w: string, nw: string}
     */
    public static function neighbors(string $hash): array
    {
        $n = self::adjacent($hash, 'n');
        $s = self::adjacent($hash, 's');

        return [
            'n' => $n,
            'ne' => self::adjacent($n, 'e'),
            'e' => self::adjacent($hash, 'e'),
            'se' => self::adjacent($s, 'e'),
            's' => $s,
            'sw' => self::adjacent($s, 'w'),
            'w' => self::adjacent($hash, 'w'),
            'nw' => self::adjacent($n, 'w'),
        ];
    }

    /**
     * Geohash prefixes (center cell + 8 neighbors) at a precision suited
     * to the given radius, guaranteed to cover the circle.
     *
     * @return list<string>
     */
    public static function coverRadius(float $lat, float $lng, float $radiusKm): array
    {
        $precision = match (true) {
            $radiusKm <= 5 => 5,
            $radiusKm <= 20 => 4,
            $radiusKm <= 80 => 3,
            default => 2,
        };

        $center = self::encode($lat, $lng, $precision);

        return array_values(array_unique([$center, ...array_values(self::neighbors($center))]));
    }

    /**
     * @return array{0: array{0: float, 1: float}, 1: array{0: float, 1: float}}
     */
    private static function bounds(string $hash): array
    {
        $hash = strtolower($hash);

        if ($hash === '') {
            throw new InvalidArgumentException('Cannot decode an empty geohash.');
        }

        $latRange = [-90.0, 90.0];
        $lngRange = [-180.0, 180.0];
        $even = true;

        foreach (str_split($hash) as $character) {
            $index = strpos(self::BASE32, $character);

            if ($index === false) {
                throw new InvalidArgumentException("Invalid geohash [{$hash}].");
            }

            for ($bit = 4; $bit >= 0; $bit--) {
                $set = ($index >> $bit) & 1;

                if ($even) {
                    $mid = ($lngRange[0] + $lngRange[1]) / 2;
                    $lngRange[$set === 1 ? 0 : 1] = $mid;
                } else {
                    $mid = ($latRange[0] + $latRange[1]) / 2;
                    $latRange[$set === 1 ? 0 : 1] = $mid;
                }

                $even = ! $even;
            }
        }

        return [$latRange, $lngRange];
    }
}
