<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Per-day product view rollup (Shop P4). */
class ProductStatsDaily extends Model
{
    protected $table = 'product_stats_daily';

    protected $fillable = ['product_id', 'date', 'views'];

    protected function casts(): array
    {
        return ['date' => 'date', 'views' => 'integer'];
    }
}
