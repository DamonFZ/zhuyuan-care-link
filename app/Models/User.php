<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        "name",
        "email",
        "password",
        "openid",
        "session_key",
        "points",
        "volunteer_hours",
        "is_captain",
    ];

    protected $hidden = [
        "password",
        "remember_token",
    ];

    protected $casts = [
        "email_verified_at" => "datetime",
        "password" => "hashed",
        "points" => "decimal:2",
        "is_captain" => "boolean",
    ];

    public function recyclingOrders(): HasMany
    {
        return $this->hasMany(RecyclingOrder::class);
    }

    public function volunteerRecords(): HasMany
    {
        return $this->hasMany(VolunteerRecord::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}