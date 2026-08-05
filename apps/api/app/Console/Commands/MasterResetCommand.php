<?php

namespace App\Console\Commands;

use App\Services\Admin\MasterResetService;
use Illuminate\Console\Command;

class MasterResetCommand extends Command
{
    protected $signature = 'app:master-reset
        {--force : Skip the typed RESET confirmation (for scripts/tests)}';

    protected $description = 'Delete ALL user-generated content and non-admin users. Keeps admins (counters zeroed), topics, badges, ranks, XP actions, business categories, settings and ad slots. Irreversible.';

    public function handle(MasterResetService $reset): int
    {
        if (! $this->option('force')) {
            $this->components->warn('This will permanently delete every non-admin user and ALL content (posts, comments, messages, media, campaigns, reports, XP history).');
            $this->components->warn('Admin accounts, topics, badges, ranks, business categories, settings and ad slots are kept.');

            if ($this->ask('Type RESET to confirm') !== 'RESET') {
                $this->components->error('Confirmation did not match. Nothing was deleted.');

                return self::FAILURE;
            }
        }

        set_time_limit(0);

        $counts = $reset->run();

        foreach ($counts as $table => $count) {
            if ($count > 0) {
                $this->components->twoColumnDetail($table, (string) $count.' deleted');
            }
        }

        $this->components->success(sprintf(
            'Master reset complete: %d rows deleted across %d tables. Admins, topics and settings retained.',
            array_sum($counts),
            count(array_filter($counts)),
        ));

        return self::SUCCESS;
    }
}
