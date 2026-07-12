<?php

use App\Models\Post;
use App\Models\User;
use App\Services\Admin\MasterResetService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

test('master reset wipes all content but keeps admins and reference data', function () {
    Artisan::call('demo:seed', ['--force' => true]);

    // An admin with engagement state that must survive with zeroed counters.
    $admin = adminWithProfile();
    $super = superAdminWithProfile();
    $admin->personalProfile->forceFill([
        'followers_count' => 9,
        'following_count' => 4,
        'posts_count' => 7,
        'xp_total' => 512,
        'rank_id' => DB::table('ranks')->value('id'),
    ])->save();

    Storage::disk('local')->put('chunks/some-session/0', 'chunk');

    Artisan::call('app:master-reset', ['--force' => true]);

    // All user-generated content is gone.
    expect(User::query()->where('is_admin', false)->count())->toBe(0)
        ->and(Post::query()->withTrashed()->count())->toBe(0)
        ->and(DB::table('comments')->count())->toBe(0)
        ->and(DB::table('messages')->count())->toBe(0)
        ->and(DB::table('conversations')->count())->toBe(0)
        ->and(DB::table('media')->count())->toBe(0)
        ->and(DB::table('reports')->count())->toBe(0)
        ->and(DB::table('campaigns')->count())->toBe(0)
        ->and(DB::table('ad_events')->count())->toBe(0)
        ->and(DB::table('xp_ledger')->count())->toBe(0)
        ->and(DB::table('reactions')->count())->toBe(0)
        ->and(DB::table('follows')->count())->toBe(0)
        ->and(DB::table('mentions')->count())->toBe(0)
        ->and(DB::table('notifications')->count())->toBe(0)
        ->and(DB::table('business_needs')->count())->toBe(0)
        ->and(DB::table('post_stats_daily')->count())->toBe(0)
        ->and(DB::table('upload_sessions')->count())->toBe(0)
        ->and(DB::table('poll_votes')->count())->toBe(0)
        ->and(DB::table('quiz_attempts')->count())->toBe(0)
        ->and(DB::table('event_rsvps')->count())->toBe(0)
        ->and(DB::table('topic_follows')->count())->toBe(0);

    // Admins survive, with pristine profiles.
    expect($admin->fresh())->not->toBeNull()
        ->and($super->fresh())->not->toBeNull();

    $profile = $admin->fresh()->personalProfile;
    expect($profile->followers_count)->toBe(0)
        ->and($profile->following_count)->toBe(0)
        ->and($profile->posts_count)->toBe(0)
        ->and($profile->xp_total)->toBe(0)
        ->and($profile->rank_id)->toBeNull();

    // Reference data survives, with topic counters zeroed.
    expect(DB::table('topics')->count())->toBeGreaterThan(0)
        ->and((int) DB::table('topics')->sum('posts_count'))->toBe(0)
        ->and((int) DB::table('topics')->sum('followers_count'))->toBe(0)
        ->and(DB::table('badges')->count())->toBeGreaterThan(0)
        ->and(DB::table('ranks')->count())->toBeGreaterThan(0)
        ->and(DB::table('xp_actions')->count())->toBeGreaterThan(0)
        ->and(DB::table('business_categories')->count())->toBeGreaterThan(0)
        ->and(DB::table('settings')->count())->toBeGreaterThan(0)
        ->and(DB::table('ad_slots')->count())->toBeGreaterThan(0);

    // Storage was cleaned on both disks.
    expect(Storage::disk('public')->allFiles('media'))->toBe([])
        ->and(Storage::disk('local')->allFiles('media'))->toBe([])
        ->and(Storage::disk('local')->allFiles('chunks'))->toBe([]);
});

test('the reset service returns per-table delete counts', function () {
    userWithProfile();
    adminWithProfile();
    Post::factory()->create();

    $counts = app(MasterResetService::class)->run();

    expect($counts)->toBeArray()
        ->and($counts['posts'])->toBe(1)
        ->and($counts['users'])->toBeGreaterThanOrEqual(1)
        ->and($counts)->toHaveKeys(['media', 'comments', 'messages', 'reports', 'campaigns', 'xp_ledger']);
});

test('app:master-reset refuses without the typed RESET confirmation', function () {
    $user = userWithProfile();

    $this->artisan('app:master-reset')
        ->expectsQuestion('Type RESET to confirm', 'nope')
        ->assertFailed();

    expect($user->fresh())->not->toBeNull();
});

test('app:master-reset runs when RESET is typed', function () {
    $user = userWithProfile();
    adminWithProfile();

    $this->artisan('app:master-reset')
        ->expectsQuestion('Type RESET to confirm', 'RESET')
        ->assertSuccessful();

    expect($user->fresh())->toBeNull()
        ->and(User::query()->where('is_admin', true)->count())->toBe(1);
});

test('app:master-reset --force works non-interactively', function () {
    $user = userWithProfile();
    adminWithProfile();

    Artisan::call('app:master-reset', ['--force' => true]);

    expect($user->fresh())->toBeNull()
        ->and(User::query()->count())->toBe(1);
});
