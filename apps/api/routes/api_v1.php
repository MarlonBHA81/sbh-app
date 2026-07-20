<?php

use App\Http\Controllers\Api\V1\AdSlotController;
use App\Http\Controllers\Api\V1\AdTrackController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\SocialAuthController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\BlockController;
use App\Http\Controllers\Api\V1\BriefController;
use App\Http\Controllers\Api\V1\BusinessCategoryController;
use App\Http\Controllers\Api\V1\BusinessDirectoryController;
use App\Http\Controllers\Api\V1\BusinessEventController;
use App\Http\Controllers\Api\V1\BusinessMatchController;
use App\Http\Controllers\Api\V1\BusinessNeedController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\ChallengeController;
use App\Http\Controllers\Api\V1\CoachController;
use App\Http\Controllers\Api\V1\ConnectionController;
use App\Http\Controllers\Api\V1\DailyController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\GoalController;
use App\Http\Controllers\Api\V1\WellnessController;
use App\Http\Controllers\Api\V1\OpportunityController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\CommentReactionController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\ConversationParticipantController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\FollowController;
use App\Http\Controllers\Api\V1\FollowRequestController;
use App\Http\Controllers\Api\V1\GamificationController;
use App\Http\Controllers\Api\V1\GeoController;
use App\Http\Controllers\Api\V1\LessonController;
use App\Http\Controllers\Api\V1\MasterclassController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\MentorController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\MessageReactionController;
use App\Http\Controllers\Api\V1\MuteController;
use App\Http\Controllers\Api\V1\MyPostController;
use App\Http\Controllers\Api\V1\MyProfileController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\PostInteractionController;
use App\Http\Controllers\Api\V1\PostReactionController;
use App\Http\Controllers\Api\V1\PostSeenController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProfileMemberController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PayFastWebhookController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\ShopController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\StoreProductController;
use App\Http\Controllers\Api\V1\ProfileImageController;
use App\Http\Controllers\Api\V1\ProfileLocationController;
use App\Http\Controllers\Api\V1\ProfileSearchController;
use App\Http\Controllers\Api\V1\Public\PublicPostController;
use App\Http\Controllers\Api\V1\Public\PublicProfileController;
use App\Http\Controllers\Api\V1\Public\PublicSitemapController;
use App\Http\Controllers\Api\V1\PushSubscriptionController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ResourceController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\StatusController;
use App\Http\Controllers\Api\V1\TopicController;
use App\Http\Controllers\Api\V1\UploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes (prefix: /api/v1)
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('register', RegisterController::class)->middleware('throttle:auth');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:auth');
    Route::post('logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('token', TokenController::class)->middleware('throttle:auth');

    Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:auth');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:auth');

    // Socialite web flow needs sessions, hence the web middleware group.
    Route::middleware('web')->group(function () {
        Route::get('{provider}/redirect', [SocialAuthController::class, 'redirect'])
            ->whereIn('provider', ['google', 'facebook', 'twitter']);
        Route::get('{provider}/callback', [SocialAuthController::class, 'callback'])
            ->whereIn('provider', ['google', 'facebook', 'twitter']);
    });
});

// Public status flags (maintenance banner, registration toggle).
Route::get('status', StatusController::class);

// Unauthenticated read endpoints for Next.js SSR metadata / SEO. No auth or
// active-profile resolution; privacy walls are enforced inside the controllers.
Route::prefix('public')->middleware('throttle:60,1')->group(function () {
    Route::get('profiles/{handle}', [PublicProfileController::class, 'show']);
    Route::get('profiles/{handle}/posts', [PublicProfileController::class, 'posts']);
    Route::get('posts/{ulid}', [PublicPostController::class, 'show']);
    Route::get('sitemap', PublicSitemapController::class);
});

// Public business category taxonomy (cached).
Route::get('business/categories', [BusinessCategoryController::class, 'index']);

// PayFast ITN (Shop P2) — server-to-server payment confirmation; unauthenticated.
Route::post('shop/payfast/itn', PayFastWebhookController::class)->middleware('throttle:60,1');

// Public profile routes (viewer resolved when authenticated).
Route::middleware('profile.active')->group(function () {
    Route::get('profiles/{handle}', [ProfileController::class, 'show']);
    Route::get('profiles/{handle}/followers', [ProfileController::class, 'followers']);
    Route::get('profiles/{handle}/following', [ProfileController::class, 'following']);

    Route::get('topics/tree', [TopicController::class, 'tree']);
    Route::get('topics/{slug}', [TopicController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'not_banned', 'profile.active'])->group(function () {
    Route::get('me', [MeController::class, 'show']);
    Route::patch('me', [MeController::class, 'update']);
    // Data-subject rights (GDPR/POPIA): export + account deletion.
    Route::get('me/export', [AccountController::class, 'export']);
    Route::delete('me/account', [AccountController::class, 'destroy'])->middleware('throttle:auth');

    Route::get('me/profiles', [MyProfileController::class, 'index']);
    Route::post('me/profiles', [MyProfileController::class, 'store']);
    Route::patch('me/profiles/{profile}', [MyProfileController::class, 'update']);
    Route::delete('me/profiles/{profile}', [MyProfileController::class, 'destroy']);

    // Team management for multi-user business profiles (Roles P4).
    Route::get('me/profiles/{profile}/members', [ProfileMemberController::class, 'index']);
    Route::post('me/profiles/{profile}/members', [ProfileMemberController::class, 'store']);
    Route::patch('me/profiles/{profile}/members/{handle}', [ProfileMemberController::class, 'updateRole']);
    Route::delete('me/profiles/{profile}/members/{handle}', [ProfileMemberController::class, 'destroy']);

    Route::post('me/profiles/{profile}/avatar', [ProfileImageController::class, 'storeAvatar']);
    Route::delete('me/profiles/{profile}/avatar', [ProfileImageController::class, 'destroyAvatar']);
    Route::post('me/profiles/{profile}/cover', [ProfileImageController::class, 'storeCover']);
    Route::delete('me/profiles/{profile}/cover', [ProfileImageController::class, 'destroyCover']);
    Route::post('me/profiles/{profile}/location', [ProfileLocationController::class, 'store']);
    Route::delete('me/profiles/{profile}/location', [ProfileLocationController::class, 'destroy']);

    Route::get('me/follow-requests', [FollowRequestController::class, 'index']);
    Route::post('me/follow-requests/{follow}/accept', [FollowRequestController::class, 'accept']);
    Route::post('me/follow-requests/{follow}/decline', [FollowRequestController::class, 'decline']);

    Route::post('profiles/{handle}/follow', [FollowController::class, 'store']);
    Route::delete('profiles/{handle}/follow', [FollowController::class, 'destroy']);

    // Safety: blocks, mutes, reports.
    Route::get('me/blocks', [BlockController::class, 'index']);
    Route::post('profiles/{handle}/block', [BlockController::class, 'store']);
    Route::delete('profiles/{handle}/block', [BlockController::class, 'destroy']);

    Route::get('me/mutes', [MuteController::class, 'index']);
    Route::post('profiles/{handle}/mute', [MuteController::class, 'store']);
    Route::delete('profiles/{handle}/mute', [MuteController::class, 'destroy']);

    Route::post('reports', [ReportController::class, 'store'])->middleware('throttle:reports');

    Route::post('media', [MediaController::class, 'store']);
    Route::get('media/{media}', [MediaController::class, 'show']);

    // Chunked video/audio uploads.
    Route::post('uploads', [UploadController::class, 'store']);
    Route::put('uploads/{upload}/chunks/{index}', [UploadController::class, 'chunk'])->whereNumber('index');
    Route::post('uploads/{upload}/complete', [UploadController::class, 'complete']);
    Route::delete('uploads/{upload}', [UploadController::class, 'destroy']);

    Route::get('me/posts', [MyPostController::class, 'index']);

    Route::post('posts/suggest-topics', [PostController::class, 'suggestTopics'])
        ->middleware('throttle:suggest-topics');

    Route::post('posts', [PostController::class, 'store']);
    Route::get('posts/{post}', [PostController::class, 'show']);
    Route::patch('posts/{post}', [PostController::class, 'update']);
    Route::delete('posts/{post}', [PostController::class, 'destroy']);
    Route::post('posts/{post}/reveal', [PostController::class, 'reveal']);

    // Batch view tracking (one view per post per profile per day).
    Route::post('posts/seen', [PostSeenController::class, 'store'])->middleware('throttle:60,1');

    Route::get('profiles/{handle}/posts', [ProfileController::class, 'posts']);

    // Ad Center: promoted-post campaign management is admin-only.
    Route::middleware('admin')->group(function () {
        Route::get('ads/campaigns', [CampaignController::class, 'index']);
        Route::post('ads/campaigns', [CampaignController::class, 'store']);
        Route::get('ads/campaigns/{campaign}', [CampaignController::class, 'show']);
        Route::patch('ads/campaigns/{campaign}', [CampaignController::class, 'update']);
        Route::delete('ads/campaigns/{campaign}', [CampaignController::class, 'destroy']);
    });

    // Tracking and sponsor slots stay open to every authenticated user —
    // impressions and clicks come from the whole audience, not just admins.
    Route::post('ads/track', [AdTrackController::class, 'store'])->middleware('throttle:120,1');
    Route::get('ads/slots/{placement}', [AdSlotController::class, 'show']);

    // Creator analytics dashboard.
    Route::get('analytics/overview', [AnalyticsController::class, 'overview']);
    Route::get('analytics/posts', [AnalyticsController::class, 'posts']);

    // Gamification: XP, ranks and leaderboards.
    Route::get('me/xp', [GamificationController::class, 'xp']);
    Route::get('gamification/leaderboard', [GamificationController::class, 'leaderboard']);
    Route::get('challenges', [ChallengeController::class, 'index']);
    // Facilitator-created challenges (Roles P2) — gated by ChallengePolicy.
    Route::post('challenges', [ChallengeController::class, 'store']);
    Route::get('challenges/{challenge}', [ChallengeController::class, 'show']);
    Route::patch('challenges/{challenge}', [ChallengeController::class, 'update']);
    Route::post('challenges/{challenge}/join', [ChallengeController::class, 'join']);
    Route::delete('challenges/{challenge}/join', [ChallengeController::class, 'leave']);
    Route::get('gamification/ranks', [GamificationController::class, 'ranks']);

    // Today's connections (V1 · CONNECT) — people to meet, with a reason.
    Route::get('me/connections', [ConnectionController::class, 'today']);

    // Mentor matching (V2 · CONNECT) — opted-in mentors ranked by relevance.
    Route::get('mentors', [MentorController::class, 'index']);

    // Daily challenge + streak (V1 · PROGRESS).
    Route::get('me/daily', [DailyController::class, 'today']);
    Route::post('me/daily/complete', [DailyController::class, 'complete']);
    Route::get('me/streak', [DailyController::class, 'streak']);

    // Business dashboard & goals (V3 · PROGRESS) — a member's growth view.
    Route::get('me/dashboard', [DashboardController::class, 'show']);
    Route::get('me/goals', [GoalController::class, 'index']);
    Route::post('me/goals', [GoalController::class, 'store']);
    Route::patch('goals/{goal}', [GoalController::class, 'update']);
    Route::delete('goals/{goal}', [GoalController::class, 'destroy']);

    // Wellness & resilience space (V3 · BELONG) — supportive, never gamified.
    Route::get('wellness/resources', [WellnessController::class, 'resources']);
    Route::get('me/wellness/checkins', [WellnessController::class, 'checkins']);
    Route::post('me/wellness/checkins', [WellnessController::class, 'storeCheckin']);

    // Opportunities (V1 · GROW) — admin-curated tenders, funding, grants.
    Route::get('opportunities', [OpportunityController::class, 'index']);
    // Fit-ranked opportunities for the member (V3 · personalised Home).
    Route::get('me/opportunities/suggested', [OpportunityController::class, 'suggested']);
    Route::get('me/opportunities/saved', [OpportunityController::class, 'saved']);
    Route::get('opportunities/{opportunity}', [OpportunityController::class, 'show']);
    Route::post('opportunities/{opportunity}/save', [OpportunityController::class, 'save']);
    Route::delete('opportunities/{opportunity}/save', [OpportunityController::class, 'unsave']);

    // Resource Library (V2 · LEARN) — admin-curated templates, checklists,
    // toolkits and AI prompts, bookmarkable like opportunities.
    Route::get('resources', [ResourceController::class, 'index']);
    Route::get('me/resources/saved', [ResourceController::class, 'saved']);
    Route::get('resources/{resource}', [ResourceController::class, 'show']);
    Route::post('resources/{resource}/save', [ResourceController::class, 'save']);
    Route::delete('resources/{resource}/save', [ResourceController::class, 'unsave']);

    // Masterclasses & cohorts (V3 · LEARN) — longer programmes members enrol in.
    Route::get('masterclasses', [MasterclassController::class, 'index']);
    Route::get('masterclasses/{masterclass}', [MasterclassController::class, 'show']);
    Route::post('masterclasses/{masterclass}/enrol', [MasterclassController::class, 'enrol']);
    Route::delete('masterclasses/{masterclass}/enrol', [MasterclassController::class, 'withdraw']);

    // Bite-size learning modules (V2 · LEARN) — short lessons that award XP.
    Route::get('learn/lessons', [LessonController::class, 'index']);
    Route::get('me/learn/progress', [LessonController::class, 'progress']);
    Route::get('learn/lessons/{lesson}', [LessonController::class, 'show']);
    Route::post('learn/lessons/{lesson}/complete', [LessonController::class, 'complete']);

    // AI Business Coach v1 (V2 · LEARN) — persisted chat; works with no API key.
    Route::get('coach', [CoachController::class, 'show']);
    // Deep Coach (V3) — fit-ranked opportunities + recommended lessons.
    Route::get('coach/suggestions', [CoachController::class, 'suggestions']);
    Route::post('coach/messages', [CoachController::class, 'store'])->middleware('throttle:coach');
    Route::delete('coach', [CoachController::class, 'destroy']);

    // Marketplace (Shop P1) — browse stores/products; vendor management under /me/store.
    Route::get('shop/stores', [ShopController::class, 'stores']);
    Route::get('shop/stores/{store}', [ShopController::class, 'store']);
    Route::get('shop/stores/{store}/products', [ShopController::class, 'storeProducts']);
    Route::get('shop/products/{product}', [ShopController::class, 'product']);
    Route::get('me/store', [StoreController::class, 'show']);
    Route::post('me/store', [StoreController::class, 'upsert']);
    Route::get('me/store/products', [StoreProductController::class, 'index']);
    Route::post('me/store/products', [StoreProductController::class, 'store']);
    Route::patch('me/store/products/{product}', [StoreProductController::class, 'update']);
    Route::delete('me/store/products/{product}', [StoreProductController::class, 'destroy']);
    Route::post('me/store/products/{product}/file', [StoreProductController::class, 'uploadFile']);

    // Checkout + orders + entitlements (Shop P2). Payment is confirmed by the ITN.
    Route::post('shop/checkout', [CheckoutController::class, 'store']);
    Route::get('shop/orders/{order}', [CheckoutController::class, 'show']);
    Route::get('me/orders', [OrderController::class, 'index']);
    Route::get('me/store/orders', [OrderController::class, 'storeOrders']);
    Route::get('me/purchases', [PurchaseController::class, 'index']);
    Route::get('me/purchases/{product}/download', [PurchaseController::class, 'download']);

    // Daily Business Brief (V2 · Feature 7) — a personalised daily card; works with no API key.
    Route::get('me/brief', [BriefController::class, 'show']);

    Route::get('me/topics', [TopicController::class, 'mine']);
    Route::post('topics/{slug}/follow', [TopicController::class, 'follow']);
    Route::delete('topics/{slug}/follow', [TopicController::class, 'unfollow']);

    Route::get('feeds/questions', [FeedController::class, 'questions']);
    Route::get('feeds/wins', [FeedController::class, 'wins']);
    Route::get('feeds/following', [FeedController::class, 'following']);
    Route::get('feeds/for-you', [FeedController::class, 'forYou']);
    Route::get('feeds/nearby', [FeedController::class, 'nearby']);
    Route::get('feeds/local', [FeedController::class, 'local']);
    Route::get('feeds/topics/{slug}', [FeedController::class, 'topic']);

    // Reactions & votes (write endpoints throttled by the 'engagement' limiter).
    Route::middleware('throttle:engagement')->group(function () {
        Route::post('posts/{post}/like', [PostReactionController::class, 'like']);
        Route::delete('posts/{post}/like', [PostReactionController::class, 'unlike']);
        Route::post('posts/{post}/vote', [PostReactionController::class, 'vote']);

        // Satellite interactions (poll vote, quiz attempt, event RSVP).
        Route::post('posts/{post}/poll-vote', [PostInteractionController::class, 'pollVote']);
        Route::post('posts/{post}/quiz-attempt', [PostInteractionController::class, 'quizAttempt']);
        Route::post('posts/{post}/rsvp', [PostInteractionController::class, 'rsvp']);

        Route::post('comments/{comment}/like', [CommentReactionController::class, 'like']);
        Route::delete('comments/{comment}/like', [CommentReactionController::class, 'unlike']);
        Route::post('comments/{comment}/vote', [CommentReactionController::class, 'vote']);

        Route::patch('comments/{comment}', [CommentController::class, 'update']);
        Route::delete('comments/{comment}', [CommentController::class, 'destroy']);

        // Post author marks a reply helpful (V1 · contribution recognition).
        Route::post('comments/{comment}/helpful', [CommentController::class, 'markHelpful']);
        Route::delete('comments/{comment}/helpful', [CommentController::class, 'unmarkHelpful']);
    });

    // Comments.
    Route::get('posts/{post}/comments', [CommentController::class, 'index']);
    Route::post('posts/{post}/comments', [CommentController::class, 'store'])->middleware('throttle:comments');
    Route::get('comments/{comment}/replies', [CommentController::class, 'replies']);

    // @mention typeahead.
    Route::get('search/profiles', [ProfileSearchController::class, 'index']);

    // Combined typeahead + full-text-ish post/geo search.
    Route::get('search', [SearchController::class, 'index']);
    Route::get('search/posts', [SearchController::class, 'posts']);

    Route::get('geo/reverse', [GeoController::class, 'reverse'])->middleware('throttle:20,1');
    Route::get('geo/nearby-users', [GeoController::class, 'nearbyUsers']);

    // Notifications (scoped to the active profile).
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'read']);

    // Web push subscriptions.
    Route::get('me/push-subscriptions/public-key', [PushSubscriptionController::class, 'publicKey']);
    Route::post('me/push-subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('me/push-subscriptions', [PushSubscriptionController::class, 'destroy']);

    // Messaging: conversations, messages, reactions, read receipts.
    Route::get('conversations', [ConversationController::class, 'index']);
    Route::post('conversations', [ConversationController::class, 'store']);
    Route::get('conversations/{conversation}', [ConversationController::class, 'show']);
    Route::patch('conversations/{conversation}', [ConversationController::class, 'update']);

    Route::get('conversations/{conversation}/messages', [MessageController::class, 'index']);
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])
        ->middleware('throttle:messages');

    Route::post('conversations/{conversation}/read', [ConversationController::class, 'read']);

    Route::post('conversations/{conversation}/participants', [ConversationParticipantController::class, 'store']);
    Route::delete('conversations/{conversation}/participants/{profile}', [ConversationParticipantController::class, 'destroy']);
    Route::post('conversations/{conversation}/participants/{profile}/role', [ConversationParticipantController::class, 'updateRole']);

    Route::delete('messages/{message}', [MessageController::class, 'destroy']);
    Route::post('messages/{message}/reactions', [MessageReactionController::class, 'store']);
    Route::delete('messages/{message}/reactions', [MessageReactionController::class, 'destroy']);

    Route::get('me/unread-messages-count', [ConversationController::class, 'unreadCount']);

    // Business directory, needs, matchmaking and events.
    Route::get('business/directory', [BusinessDirectoryController::class, 'index']);
    Route::get('business/matches', [BusinessMatchController::class, 'index']);
    Route::get('business/events', [BusinessEventController::class, 'index']);

    Route::get('me/business-needs', [BusinessNeedController::class, 'index']);
    Route::post('me/business-needs', [BusinessNeedController::class, 'store']);
    Route::patch('me/business-needs/{businessNeed}', [BusinessNeedController::class, 'update']);
    Route::delete('me/business-needs/{businessNeed}', [BusinessNeedController::class, 'destroy']);
});
