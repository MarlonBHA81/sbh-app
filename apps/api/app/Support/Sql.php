<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class Sql
{
    /**
     * Guarded spherical-law-of-cosines distance in km, with three positional
     * bindings: [lat, lng, lat]. The acos() argument is clamped to [-1, 1]
     * against floating-point drift. SQLite only has the two-argument scalar
     * min()/max(); MySQL/MariaDB/Postgres use least()/greatest() (their
     * MIN/MAX are aggregates), so the clamp is driver-dependent.
     */
    public static function haversine(string $latColumn = 'lat', string $lngColumn = 'lng'): string
    {
        [$least, $greatest] = DB::connection()->getDriverName() === 'sqlite'
            ? ['min', 'max']
            : ['least', 'greatest'];

        return "(6371 * acos({$least}(1.0, {$greatest}(-1.0,"
            ." cos(radians(?)) * cos(radians({$latColumn})) * cos(radians({$lngColumn}) - radians(?))"
            ." + sin(radians(?)) * sin(radians({$latColumn}))))))";
    }
}
