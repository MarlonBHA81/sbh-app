<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeAdminCommand extends Command
{
    protected $signature = 'make:admin {email} {--name=} {--password=} {--super}';

    protected $description = 'Create or promote a user to admin (grants Filament panel access). Pass --super to also grant super-admin (integration settings + platform analytics); super admin always implies admin.';

    public function handle(ProfileService $profiles): int
    {
        $email = $this->argument('email');
        $super = (bool) $this->option('super');

        // Super admin implies admin — a super admin is always an admin too.
        $flags = ['is_admin' => true];
        if ($super) {
            $flags['is_super_admin'] = true;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $user->forceFill($flags)->save();
            $this->info($super
                ? "Promoted existing user {$email} to super admin (admin + super admin)."
                : "Promoted existing user {$email} to admin.");

            return self::SUCCESS;
        }

        $name = $this->option('name') ?: $this->ask('Name', 'Admin');
        $password = $this->option('password') ?: $this->secret('Password');

        if (! $password) {
            $this->error('A password is required.');

            return self::FAILURE;
        }

        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $user->forceFill(array_merge($flags, [
            'email_verified_at' => now(),
        ]))->save();

        // Give the new admin a personal profile so the rest of the app works.
        $profiles->createPersonalProfile($user);

        $this->info($super
            ? "Created super admin user {$email} (admin + super admin)."
            : "Created admin user {$email}.");

        return self::SUCCESS;
    }
}
