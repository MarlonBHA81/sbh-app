<?php

namespace App\Console\Commands;

use App\Models\UploadSession;
use App\Services\UploadService;
use Illuminate\Console\Command;

class PruneUploadSessions extends Command
{
    protected $signature = 'uploads:prune {--hours=24 : Age in hours after which a stale session is pruned}';

    protected $description = 'Abort and clean up stale chunked-upload sessions that never completed';

    public function handle(UploadService $uploads): int
    {
        $cutoff = now()->subHours((int) $this->option('hours'));
        $count = 0;

        UploadSession::query()
            ->whereIn('status', [UploadSession::STATUS_PENDING, UploadSession::STATUS_ASSEMBLING])
            ->where('created_at', '<', $cutoff)
            ->each(function (UploadSession $session) use ($uploads, &$count) {
                $uploads->abort($session);
                $count++;
            });

        $this->info("Pruned {$count} stale upload session(s).");

        return self::SUCCESS;
    }
}
