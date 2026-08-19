<?php

/*
|--------------------------------------------------------------------------
| Application version & changelog
|--------------------------------------------------------------------------
|
| The single source of truth for the version shown in the admin panel footer
| and the entries listed on the admin Changelog page. Bump `number` / `released`
| and prepend a `releases` entry on every production release — that footer
| version is how you confirm a deploy actually landed.
|
*/

return [

    'number' => env('APP_VERSION', '1.2.2'),

    'released' => '2026-08-11',

    // Newest first. Each entry: version, date (Y-m-d), title, list of changes.
    'releases' => [
        [
            'version' => '1.2.2',
            'date' => '2026-08-11',
            'title' => 'Floating bug-report button',
            'changes' => [
                'A persistent bug icon in the app now lets members report a problem from any screen (opens the existing report dialog, which captures page context automatically).',
            ],
        ],
        [
            'version' => '1.2.1',
            'date' => '2026-08-11',
            'title' => 'Official CIPC API driver',
            'changes' => [
                'CIPC verification can now call the official CIPC "Public Data - Commercial" API (apim.cipc.co.za) via CIPC_DRIVER=cipc — POST /information with the enterprise number, using an APIM subscription key plus a static or client-credentials OAuth token.',
            ],
        ],
        [
            'version' => '1.2.0',
            'date' => '2026-08-11',
            'title' => 'Personal/business onboarding + CIPC-verified businesses',
            'changes' => [
                'Signup now clearly sets up your personal profile; a first-login prompt invites you to open a business profile.',
                'Business profiles are hard-gated on CIPC: creation requires a registration number that CIPC confirms, and the legal name is taken from CIPC.',
                'The onboarding checklist is profile-aware — personal profiles no longer show business-only steps.',
                'New feature flags to hide Home tiles: community (mentors/Q&A/forums), promoted posts, business directory, and business tools.',
            ],
        ],
        [
            'version' => '1.1.0',
            'date' => '2026-08-09',
            'title' => 'Security & operations hardening',
            'changes' => [
                'Member two-factor authentication (TOTP) with recovery codes and a login challenge.',
                'Upload virus scanning (ClamAV) that quarantines infected files.',
                'Operational readiness: deep /api/v1/health probe, container healthchecks, and off-box backups.',
                'CIPC business-registration verification with a "CIPC verified" sticker and XP reward.',
                'Admin changelog and a version stamp in the admin panel footer.',
            ],
        ],
        [
            'version' => '1.0.0',
            'date' => '2026-08-03',
            'title' => 'Platform general availability',
            'changes' => [
                'Admin two-factor authentication for the /admin panel.',
                'Enterprise & Supplier Development (ESD) portal: programmes, cohorts, enrolments, tracking and reporting.',
                'Business verification, gamification, commerce, messaging, live rooms, discovery and observability.',
            ],
        ],
    ],

];
