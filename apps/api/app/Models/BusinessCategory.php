<?php

namespace App\Models;

use Database\Factories\BusinessCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessCategory extends Model
{
    /** @use HasFactory<BusinessCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'icon',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class);
    }

    public function businessNeeds(): HasMany
    {
        return $this->hasMany(BusinessNeed::class);
    }
}
