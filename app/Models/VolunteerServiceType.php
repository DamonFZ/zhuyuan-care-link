<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VolunteerServiceType extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        "name",
        "base_reward_rate",
    ];

    protected $casts = [
        "base_reward_rate" => "decimal:2",
    ];

    public function volunteerRecords(): HasMany
    {
        return $this->hasMany(VolunteerRecord::class, "volunteer_service_type_id");
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }
}
