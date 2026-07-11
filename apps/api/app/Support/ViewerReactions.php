<?php

namespace App\Support;

use App\Models\Profile;
use App\Models\Reaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Bulk-hydrates viewer engagement state (liked + my_vote) onto a page of
 * reactable models so resources avoid an N+1 lookup per item.
 */
class ViewerReactions
{
    /**
     * @param  iterable<int, Model>  $reactables
     */
    public static function hydrate(iterable $reactables, ?Profile $viewer): void
    {
        /** @var Collection<int, Model> $models */
        $models = collect($reactables)->filter()->values();

        if ($models->isEmpty()) {
            return;
        }

        if ($viewer === null) {
            $models->each(fn ($model) => $model->setViewerState(false, 0));

            return;
        }

        $byType = $models->groupBy(fn ($model) => $model->getMorphClass());

        $reactions = collect();

        foreach ($byType as $type => $group) {
            $rows = Reaction::query()
                ->where('profile_id', $viewer->id)
                ->where('reactable_type', $type)
                ->whereIn('reactable_id', $group->map->getKey()->all())
                ->get();

            $reactions = $reactions->merge($rows);
        }

        $indexed = $reactions->groupBy(fn (Reaction $r) => $r->reactable_type.':'.$r->reactable_id);

        foreach ($models as $model) {
            $key = $model->getMorphClass().':'.$model->getKey();
            $rows = $indexed->get($key, collect());

            $liked = $rows->firstWhere('bucket', Reaction::BUCKET_LIKE) !== null;

            $vote = $rows->firstWhere('bucket', Reaction::BUCKET_VOTE);
            $myVote = match ($vote?->kind) {
                Reaction::KIND_UP => 1,
                Reaction::KIND_DOWN => -1,
                default => 0,
            };

            $model->setViewerState($liked, $myVote);
        }
    }
}
