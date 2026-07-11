<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    /** Transient: the current viewer's attempt (hydrated per request), or null. */
    public ?QuizAttempt $viewerAttempt = null;

    protected $table = 'quizzes';

    protected $fillable = [
        'post_id',
        'attempts_count',
    ];

    protected function casts(): array
    {
        return [
            'attempts_count' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
