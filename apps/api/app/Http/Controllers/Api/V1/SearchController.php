<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Resources\ProfileResource;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Topic;
use App\Services\Feed\FeedService;
use App\Services\Posts\PostService;
use App\Services\SafetyService;
use App\Support\ViewerReactions;
use App\Support\ViewerSatelliteState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SearchController extends Controller
{
    /** Max results per bucket in the combined typeahead. */
    private const TYPEAHEAD_LIMIT = 6;

    public function __construct(private SafetyService $safety) {}

    /**
     * Combined typeahead over profiles and topics. Profiles match a prefix or
     * substring of handle/name; topics match a substring of name/slug. Blocked
     * profiles (either direction) are excluded.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:50'],
        ]);

        $term = mb_strtolower(trim($validated['q']));

        /** @var Profile|null $viewer */
        $viewer = $request->attributes->get('activeProfile');
        $excluded = $viewer ? $this->safety->blockedProfileIds($viewer) : [];

        // Scout (database engine) matches the term as a substring across the
        // Profile searchable columns (handle, name, bio). Blocked profiles and
        // ordering stay in the underlying query via the query() callback.
        $profiles = Profile::search($term)
            ->query(fn ($query) => $query
                ->when($excluded !== [], fn ($q) => $q->whereNotIn('id', $excluded))
                ->orderBy('handle'))
            ->take(self::TYPEAHEAD_LIMIT)
            ->get();

        $topics = Topic::query()
            ->where(function ($query) use ($term) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.$term.'%'])
                    ->orWhere('slug', 'like', '%'.$term.'%');
            })
            ->orderBy('name')
            ->limit(self::TYPEAHEAD_LIMIT)
            ->get();

        return response()->json([
            'profiles' => ProfileResource::collection($profiles),
            'topics' => $topics->map(fn (Topic $topic) => [
                'id' => $topic->id,
                'slug' => $topic->slug,
                'name' => $topic->name,
                'icon' => $topic->icon,
                'followers_count' => $topic->followers_count,
            ])->values(),
        ]);
    }

    /**
     * Full-text-ish search over published public posts (case-insensitive body
     * substring), newest first, cursor-paginated and safety-filtered.
     */
    public function posts(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $term = mb_strtolower(trim($validated['q']));

        /** @var Profile|null $viewer */
        $viewer = $request->attributes->get('activeProfile');
        $excluded = $viewer ? $this->safety->feedExcludedProfileIds($viewer) : [];

        // Post::search()/toSearchableArray('body') is wired for Scout, but this
        // endpoint stays on a LIKE inside the Eloquent query: Scout's database
        // engine cannot cursorPaginate, and bridging via ->search()->keys()
        // would load every matching post id into memory before paginating — a
        // scale regression on the largest table. When a hosted engine
        // (Meilisearch/Typesense) is adopted this swaps to Post::search().
        $posts = Post::query()
            ->published()
            ->where('visibility', Post::VISIBILITY_PUBLIC)
            ->whereHas('profile', fn ($profile) => $profile->where('is_private', false))
            ->whereRaw('LOWER(body) LIKE ?', ['%'.$term.'%'])
            ->when($excluded !== [], fn ($query) => $query->whereNotIn('profile_id', $excluded))
            ->with(PostService::EAGER)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->cursorPaginate(FeedService::PER_PAGE);

        ViewerReactions::hydrate($posts->getCollection(), $viewer);
        ViewerSatelliteState::hydrate($posts->getCollection(), $viewer);

        return PostResource::collection($posts);
    }
}
