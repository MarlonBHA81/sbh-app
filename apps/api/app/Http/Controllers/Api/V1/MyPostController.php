<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\Profile;
use App\Services\Posts\PostService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class MyPostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in([Post::STATUS_DRAFT, Post::STATUS_SCHEDULED, Post::STATUS_PUBLISHED])],
        ]);

        /** @var Profile|null $profile */
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile, 403, 'An active profile is required.');

        $posts = Post::query()
            ->where('profile_id', $profile->id)
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->with(PostService::EAGER)
            ->orderByDesc('id')
            ->cursorPaginate(20);

        return PostResource::collection($posts);
    }
}
