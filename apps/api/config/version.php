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

    'number' => env('APP_VERSION', '1.1.0'),

    'released' => '2026-08-09',

    // Newest first. Each entry: version, date (Y-m-d), title, list of changes.
    'releases' => [
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
