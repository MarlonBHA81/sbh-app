<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use App\Services\Admin\MasterResetService;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Console\Command;

class DemoSeedCommand extends Command
{
    protected $signature = 'demo:seed
        {--fresh : Delete existing demo users (and their content) before seeding}
        {--force : Skip the confirmation prompt when demo data already exists}';

    protected $description = 'Seed rich South-African small-business demo content (users, posts of every type, messaging, ads, analytics). Demo users live at *@demo.sbh; use --fresh to wipe and reseed them.';

    public function handle(MasterResetService $reset): int
    {
        $existing = User::query()->where('email', 'like', '%@demo.sbh')->count();

        if ($existing > 0) {
            if ($this->option('fresh')) {
                $this->components->task(
                    "Deleting {$existing} existing demo user(s) and their content",
                    fn () => $reset->deleteDemoUsers(),
                );
            } elseif (! $this->option('force') && ! $this->confirm(
                "{$existing} demo user(s) already exist. Seeding again will DUPLICATE demo content. Continue anyway? (use --fresh to reseed cleanly)",
            )) {
                $this->components->info('Aborted. Run with --fresh to wipe and reseed the demo dataset.');

                return self::SUCCESS;
            }
        }

        // Image/audio generation and service-driven content take a while.
        set_time_limit(0);

        $this->components->info('Seeding demo content…');

        app(DemoContentSeeder::class)->setContainer(app())->setCommand($this)->run();

        foreach ($this->summary() as $label => $count) {
            $this->components->twoColumnDetail($label, (string) $count);
        }

        $this->components->success('Demo content seeded. All demo users log in with password "password".');

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function summary(): array
    {
        $demoUserIds = User::query()->where('email', 'like', '%@demo.sbh')->pluck('id');
        $profileIds = Profile::query()->whereIn('user_id', $demoUserIds)->pluck('id');

        return [
            'Demo users' => $demoUserIds->count(),
            'Profiles' => $profileIds->count(),
            'Posts' => Post::query()->whereIn('profile_id', $profileIds)->count(),
            'Comments' => Comment::query()->whereIn('profile_id', $profileIds)->count(),
            'Conversations' => Conversation::query()->whereIn('created_by_profile_id', $profileIds)->count(),
            'Campaigns' => Campaign::query()->whereIn('profile_id', $profileIds)->count(),
            'Reports' => Report::query()->whereIn('reporter_profile_id', $profileIds)->count(),
        ];
    }
}
