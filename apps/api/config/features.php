<?php

/*
|--------------------------------------------------------------------------
| Feature flags
|--------------------------------------------------------------------------
|
| The canonical registry of super-admin togglable features. Each flag has a
| label, a short description (both shown on the Filament "Feature flags" page),
| a default state, and a group for layout. The App\Support\Features helper
| overlays any Setting override ('features.<key>') on top of these defaults;
| the resolved map is surfaced to the web client via /api/v1/me.
|
| To add a feature toggle: add an entry here, gate the server behaviour with
| Features::enabled('<key>'), and gate the client with useFeature('<key>').
|
*/

return [
    'flags' => [
        'daily_brief' => [
            'label' => 'Daily Business Brief',
            'description' => 'The personalised daily headline + curated items on Home.',
            'default' => true,
            'group' => 'Home & content',
        ],
        'ai_curation' => [
            'label' => 'AI-personalised brief',
            'description' => 'Let AI curate each member’s brief items to their activity. '
                .'Falls back to industry matching when off or no AI key is set.',
            'default' => true,
            'group' => 'Home & content',
        ],
        'coach' => [
            'label' => 'AI Coach',
            'description' => 'The conversational business coach.',
            'default' => true,
            'group' => 'Home & content',
        ],
        'opportunities' => [
            'label' => 'Opportunities & tenders',
            'description' => 'The opportunities feed (grants, tenders, calls).',
            'default' => true,
            'group' => 'Home & content',
        ],
        'shop' => [
            'label' => 'Shop / marketplace',
            'description' => 'Storefronts, products, checkout and purchases.',
            'default' => true,
            'group' => 'Commerce & learning',
        ],
        'courses' => [
            'label' => 'Courses',
            'description' => 'Course products, curriculum and the course player.',
            'default' => true,
            'group' => 'Commerce & learning',
        ],
        'masterclasses' => [
            'label' => 'Masterclasses & live rooms',
            'description' => 'Masterclass rooms, live streaming, chat and replays.',
            'default' => true,
            'group' => 'Commerce & learning',
        ],
        'gamification' => [
            'label' => 'Gamification',
            'description' => 'XP, ranks, badges, streaks and leaderboards.',
            'default' => true,
            'group' => 'Engagement',
        ],
        'wellness' => [
            'label' => 'Wellness check-ins',
            'description' => 'The founder wellness check-in surface.',
            'default' => true,
            'group' => 'Engagement',
        ],
    ],
];
