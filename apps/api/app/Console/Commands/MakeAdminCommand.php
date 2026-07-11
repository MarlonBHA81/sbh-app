<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeAdminCommand extends Command
{
    protected $signature = 'make:admin {email} {--name=} {--password=}';

    protected $description = 'Create or promote a user to admin (grants Filament panel access).';

    public function handle(ProfileService $profiles): int
    {
        $email = $this->argument('email');

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $user->forceFill(['is_admin' => true])->save();
            $this->info("Promoted existing user {$email} to admin.");

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
        $user->forceFill([
            'is_admin' => true,
            'email_verified_at' => now(),
        ])->save();

        // Give the new admin a personal profile so the rest of the app works.
        $profiles->createPersonalProfile($user);

        $this->info("Created admin user {$email}.");

        return self::SUCCESS;
    }
}
