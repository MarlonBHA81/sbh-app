<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Profile;
use App\Services\Engagement\ReactionService;
use App\Support\ViewerReactions;
use Illuminate\Http\Request;

class CommentReactionController extends Controller
{
    public function __construct(private ReactionService $reactions) {}

    public function like(Request $request, Comment $comment): CommentResource
    {
        $actor = $this->actor($request, $comment);

        $this->reactions->like($actor, $comment);

        return $this->resource($comment, $actor);
    }

    public function unlike(Request $request, Comment $comment): CommentResource
    {
        $actor = $this->actor($request, $comment);

        $this->reactions->unlike($actor, $comment);

        return $this->resource($comment, $actor);
    }

    public function vote(Request $request, Comment $comment): CommentResource
    {
        $actor = $this->actor($request, $comment);

        $validated = $request->validate([
            'value' => ['required', 'integer', 'in:1,-1,0'],
        ]);

        $this->reactions->vote($actor, $comment, (int) $validated['value']);

        return $this->resource($comment, $actor);
    }

    private function actor(Request $request, Comment $comment): Profile
    {
        $actor = $request->attributes->get('activeProfile');

        abort_unless($actor instanceof Profile, 422, 'No active profile.');
        abort_unless($comment->post->isVisibleTo($actor), 404);

        return $actor;
    }

    private function resource(Comment $comment, Profile $actor): CommentResource
    {
        $comment = $comment->fresh()->load('profile');

        ViewerReactions::hydrate([$comment], $actor);

        return new CommentResource($comment);
    }
}
