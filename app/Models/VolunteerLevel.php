<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VolunteerLevel extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        "name",
        "multiplier",
    ];

    protected $casts = [
        "multiplier" => "decimal:2",
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, "volunteer_level_id");
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }
}
