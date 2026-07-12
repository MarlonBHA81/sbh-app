<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostStatsDaily extends Model
{
    protected $table = 'post_stats_daily';

    public $timestamps = false;

    protected $fillable = [
        'post_id',
        'date',
        'views',
        'likes',
        'comments',
        'reposts',
        'votes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'views' => 'integer',
            'likes' => 'integer',
            'comments' => 'integer',
            'reposts' => 'integer',
            'votes' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
