<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Profile;
use App\Services\Engagement\CommentService;
use App\Support\ViewerReactions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class CommentController extends Controller
{
    private const PER_PAGE = 20;

    private const PRELOADED_REPLIES = 2;

    public function __construct(private CommentService $comments) {}

    /**
     * Top-level comments (newest first) with the first replies preloaded.
     * Soft-deleted comments that still have replies are kept as tombstones.
     */
    public function index(Request $request, Post $post): AnonymousResourceCollection
    {
        $viewer = $request->attributes->get('activeProfile');

        abort_unless($post->isVisibleTo($viewer), 404);

        $comments = $post->comments()
            ->withTrashed()
            ->whereNull('parent_comment_id')
            ->where(fn ($query) => $query->whereNull('deleted_at')->orWhere('replies_count', '>', 0))
            ->with([
                'profile',
                'replies' => fn ($query) => $query->with('profile')->limit(self::PRELOADED_REPLIES),
            ])
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);

        $this->hydrate($comments->getCollection(), $viewer);

        return CommentResource::collection($comments);
    }

    public function store(Request $request, Post $post): CommentResource
    {
        $author = $this->activeProfile($request);

        abort_unless($post->isVisibleTo($author), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
            'parent_comment_id' => ['nullable', 'string'],
        ]);

        $comment = $this->comments->create($author, $post, $data);

        ViewerReactions::hydrate([$comment], $author);

        return new CommentResource($comment);
    }

    public function replies(Request $request, Comment $comment): AnonymousResourceCollection
    {
        $viewer = $request->attributes->get('activeProfile');

        abort_unless($comment->post->isVisibleTo($viewer), 404);

        $replies = $comment->replies()
            ->with('profile')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE);

        $replies->getCollection()->each(fn (Comment $reply) => $reply->setRelation('parent', $comment));

        ViewerReactions::hydrate($replies->getCollection(), $viewer);

        return CommentResource::collection($replies);
    }

    public function update(Request $request, Comment $comment): CommentResource
    {
        abort_unless($this->isAuthor($request, $comment), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $updated = $this->comments->update($comment, $data['body']);

        ViewerReactions::hydrate([$updated], $request->attributes->get('activeProfile'));

        return new CommentResource($updated->load('profile'));
    }

    public function destroy(Request $request, Comment $comment): Response
    {
        abort_unless(
            $this->isAuthor($request, $comment) || $this->isPostAuthor($request, $comment),
            403
        );

        $this->comments->delete($comment);

        return response()->noContent();
    }

    /**
     * @param  Collection<int, Comment>  $comments
     */
    private function hydrate($comments, ?Profile $viewer): void
    {
        $comments->each(function (Comment $comment) {
            if ($comment->relationLoaded('replies')) {
                $comment->replies->each(fn (Comment $reply) => $reply->setRelation('parent', $comment));
            }
        });

        $all = collect($comments)->concat(
            collect($comments)->flatMap(fn (Comment $comment) => $comment->relationLoaded('replies') ? $comment->replies : [])
        );

        ViewerReactions::hydrate($all, $viewer);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }

    private function isAuthor(Request $request, Comment $comment): bool
    {
        return $comment->profile->user_id === $request->user()->id;
    }

    private function isPostAuthor(Request $request, Comment $comment): bool
    {
        return $comment->post->profile->user_id === $request->user()->id;
    }
}
