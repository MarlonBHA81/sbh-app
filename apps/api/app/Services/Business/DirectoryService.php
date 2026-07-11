<?php

namespace App\Services\Business;

use App\Models\Profile;
use App\Services\SafetyService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

class DirectoryService
{
    public const PER_PAGE = 20;

    public function __construct(private SafetyService $safety) {}

    /**
     * Paginated directory of business profiles, filtered and safety-scoped for
     * the given viewer.
     *
     * @param  array{category?: ?string, q?: ?string, country?: ?string, city?: ?string}  $filters
     */
    public function search(Profile $viewer, array $filters): CursorPaginator
    {
        $excluded = $this->safety->blockedProfileIds($viewer);

        return Profile::query()
            ->business()
            ->whereHas('user', fn (Builder $user) => $user->whereNull('banned_at'))
            ->when($excluded !== [], fn (Builder $query) => $query->whereNotIn('id', $excluded))
            ->when(
                isset($filters['category']) && $filters['category'] !== null,
                fn (Builder $query) => $query->whereHas(
                    'businessCategory',
                    fn (Builder $category) => $category->where('slug', $filters['category'])
                )
            )
            ->when(
                isset($filters['q']) && $filters['q'] !== null,
                function (Builder $query) use ($filters) {
                    $term = '%'.$filters['q'].'%';

                    $query->where(function (Builder $inner) use ($term) {
                        $inner->where('name', 'like', $term)
                            ->orWhere('handle', 'like', $term)
                            ->orWhere('bio', 'like', $term);
                    });
                }
            )
            ->when(
                isset($filters['country']) && $filters['country'] !== null,
                fn (Builder $query) => $query->where('country_code', $filters['country'])
            )
            ->when(
                isset($filters['city']) && $filters['city'] !== null,
                fn (Builder $query) => $query->where('city', $filters['city'])
            )
            ->with(['badges', 'businessCategory'])
            ->orderByDesc('is_verified')
            ->orderByDesc('followers_count')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
