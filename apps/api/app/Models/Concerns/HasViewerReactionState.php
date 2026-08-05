<?php

namespace App\Models\Concerns;

/**
 * Transient per-request viewer engagement state for a reactable model
 * (post or comment). Populated in bulk by App\Support\ViewerReactions so
 * resources can expose `liked`/`my_vote` without an N+1 query per item.
 */
trait HasViewerReactionState
{
    protected ?bool $viewerLiked = null;

    protected ?int $viewerVote = null;

    public function setViewerState(bool $liked, int $vote): static
    {
        $this->viewerLiked = $liked;
        $this->viewerVote = $vote;

        return $this;
    }

    public function viewerLiked(): bool
    {
        return $this->viewerLiked ?? false;
    }

    public function viewerVote(): int
    {
        return $this->viewerVote ?? 0;
    }
}
