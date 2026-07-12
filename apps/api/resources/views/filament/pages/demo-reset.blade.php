<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Load demo content</x-slot>
        <x-slot name="description">
            Seeds a rich South-African small-business dataset: 20 profiles across Johannesburg, Cape Town and Durban,
            posts of every type (polls, quizzes, events, jobs, blogs, audio and more), threaded comments, DMs and a
            group chat, business needs with live matchmaking, ad campaigns with 14 days of events, 30 days of
            analytics history and a pending moderation queue.
        </x-slot>

        <div class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Demo users are marked with <code class="font-mono text-primary-600 dark:text-primary-400">*@demo.sbh</code>
                email addresses and all sign in with the password <code class="font-mono">password</code>.
                They can be removed at any time by reseeding fresh or via the master reset below.
            </p>

            @if ($this->demoUsersExist())
                <div class="rounded-lg bg-warning-50 p-4 text-sm text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400">
                    Demo content is already loaded. Seeding again would duplicate it — use
                    <strong>Reseed fresh</strong> to wipe the demo dataset and load a clean copy.
                </div>
            @endif

            <div class="flex items-center gap-3">
                {{ $this->loadDemoAction }}
                {{ $this->reseedDemoAction }}
            </div>
        </div>
    </x-filament::section>

    <x-filament::section class="ring-2 ring-danger-500/50">
        <x-slot name="heading">
            <span class="text-danger-600 dark:text-danger-400">Danger zone: master reset</span>
        </x-slot>
        <x-slot name="description">
            Wipe the entire platform back to a clean slate.
        </x-slot>

        <div class="space-y-4">
            <div class="rounded-lg bg-danger-50 p-4 text-sm text-danger-700 ring-1 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400">
                <p class="font-semibold">This action is irreversible.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li><strong>Deleted:</strong> every non-admin user, and ALL content — posts, comments, messages, media files, follows, campaigns, reports, notifications and XP history (including content created by admins).</li>
                    <li><strong>Kept:</strong> admin &amp; super-admin accounts (profile counters reset to zero), topics, badges, ranks, XP actions, business categories, global settings and ad slots.</li>
                </ul>
            </div>

            <p class="text-sm text-gray-600 dark:text-gray-400">
                You will be asked to type <code class="font-mono font-semibold text-danger-600 dark:text-danger-400">RESET</code>
                to confirm before anything is deleted.
            </p>

            {{ $this->masterResetAction }}
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
