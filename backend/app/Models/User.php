<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'company_name', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MEDIA_OWNER = 'media_owner';

    public const ROLE_ADVERTISER = 'advertiser';

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === self::ROLE_ADMIN && $this->status === 'active';
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isMediaOwner(): bool
    {
        return $this->role === self::ROLE_MEDIA_OWNER;
    }

    public function isAdvertiser(): bool
    {
        return $this->role === self::ROLE_ADVERTISER;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function mediaOwnerProfile(): HasOne
    {
        return $this->hasOne(MediaOwnerProfile::class);
    }

    public function advertiserProfile(): HasOne
    {
        return $this->hasOne(AdvertiserProfile::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'media_owner_id');
    }

    public function campaignsAsAdvertiser(): HasMany
    {
        return $this->hasMany(Campaign::class, 'advertiser_id');
    }
}
