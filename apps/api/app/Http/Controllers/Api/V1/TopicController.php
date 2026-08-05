<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TopicResource;
use App\Models\Profile;
use App\Models\Topic;
use App\Models\TopicFollow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TopicController extends Controller
{
    public const TREE_CACHE_KEY = 'topics:tree';

    private const TREE_CACHE_MINUTES = 10;

    /**
     * Full topic tree. The viewer-independent tree is cached for 10 minutes;
     * is_following is layered on per request when authenticated.
     */
    public function tree(Request $request): JsonResponse
    {
        $tree = Cache::remember(
            self::TREE_CACHE_KEY,
            now()->addMinutes(self::TREE_CACHE_MINUTES),
            fn () => $this->buildTree(),
        );

        /** @var Profile|null $viewer */
        $viewer = $request->attributes->get('activeProfile');

        if ($viewer !== null) {
            $followedIds = TopicFollow::query()
                ->where('profile_id', $viewer->id)
                ->pluck('topic_id')
                ->flip()
                ->all();

            $tree = $this->annotateFollowing($tree, $followedIds);
        }

        return response()->json(['data' => $tree]);
    }

    public function show(Request $request, string $slug): TopicResource
    {
        $topic = $this->resolveBySlug($slug)->load('children');

        return new TopicResource($topic);
    }

    public function follow(Request $request, string $slug): JsonResponse
    {
        $viewer = $this->activeProfile($request);
        $topic = $this->resolveBySlug($slug);

        DB::transaction(function () use ($viewer, $topic) {
            $follow = TopicFollow::query()->firstOrCreate([
                'profile_id' => $viewer->id,
                'topic_id' => $topic->id,
            ]);

            if ($follow->wasRecentlyCreated) {
                $topic->increment('followers_count');
            }
        });

        return response()->json([
            'data' => [
                'slug' => $topic->slug,
                'is_following' => true,
                'followers_count' => $topic->refresh()->followers_count,
            ],
        ], 201);
    }

    public function unfollow(Request $request, string $slug): Response
    {
        $viewer = $this->activeProfile($request);
        $topic = $this->resolveBySlug($slug);

        DB::transaction(function () use ($viewer, $topic) {
            $deleted = TopicFollow::query()
                ->where('profile_id', $viewer->id)
                ->where('topic_id', $topic->id)
                ->delete();

            if ($deleted > 0 && $topic->followers_count > 0) {
                $topic->decrement('followers_count');
            }
        });

        return response()->noContent();
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        $viewer = $this->activeProfile($request);

        $topics = Topic::query()
            ->join('topic_follows', 'topic_follows.topic_id', '=', 'topics.id')
            ->where('topic_follows.profile_id', $viewer->id)
            ->orderBy('topic_follows.id')
            ->select('topics.*')
            ->get();

        return TopicResource::collection($topics);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildTree(): array
    {
        $byParent = Topic::query()
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Topic $topic) => $topic->parent_id ?? 0);

        $build = function (int $parentId) use (&$build, $byParent): array {
            return ($byParent->get($parentId) ?? collect())
                ->map(fn (Topic $topic) => [
                    'id' => $topic->id,
                    'slug' => $topic->slug,
                    'name' => $topic->name,
                    'icon' => $topic->icon,
                    'followers_count' => $topic->followers_count,
                    'children' => $build($topic->id),
                ])
                ->values()
                ->all();
        };

        return $build(0);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<int, int>  $followedIds
     * @return list<array<string, mixed>>
     */
    private function annotateFollowing(array $nodes, array $followedIds): array
    {
        return array_map(function (array $node) use ($followedIds) {
            $node['is_following'] = array_key_exists($node['id'], $followedIds);
            $node['children'] = $this->annotateFollowing($node['children'], $followedIds);

            return $node;
        }, $nodes);
    }

    private function resolveBySlug(string $slug): Topic
    {
        return Topic::query()->where('slug', $slug)->firstOrFail();
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
