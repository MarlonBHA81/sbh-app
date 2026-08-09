<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPushSubscriptions,
        InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
        'timezone',
        'settings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_super_admin' => 'boolean',
            'banned_at' => 'datetime',
            'settings' => 'array',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Whether member TOTP two-factor is fully set up. A secret alone is not
     * enough — it must be confirmed (the user proved they can generate a valid
     * code) before it gates login. Distinct from the Filament admin MFA.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class);
    }

    public function personalProfile(): HasOne
    {
        return $this->hasOne(Profile::class)->where('kind', Profile::KIND_PERSONAL);
    }

    public function businessProfiles(): HasMany
    {
        return $this->hasMany(Profile::class)->where('kind', Profile::KIND_BUSINESS);
    }

    /**
     * Business profiles this user can act under via membership (Roles P4) —
     * includes ones they created (backfilled as owner) and ones they were added
     * to. Personal profiles are never shared, so they don't appear here.
     */
    public function memberProfiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'profile_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    /** Platform staff: can reach the admin panel and moderate. */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Highest tier: full control over everything, including granting the admin
     * tier and reaching platform/integration surfaces. Implies admin.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /**
     * Only admins may reach the Filament admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_admin;
    }
}
