<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Per-day store view rollup (Shop P4). */
class StoreStatsDaily extends Model
{
    protected $table = 'store_stats_daily';

    protected $fillable = ['store_id', 'date', 'views'];

    protected function casts(): array
    {
        return ['date' => 'date', 'views' => 'integer'];
    }
}
