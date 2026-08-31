<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class VolunteerRecord extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        "user_id",
        "title",
        "base_hours",
        "multiplier",
        "final_hours",
        "volunteer_service_type_id",
        "reward_points",
        "status",
    ];

    protected $casts = [
        "multiplier" => "decimal:1",
        "final_hours" => "decimal:1",
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function volunteerServiceType(): BelongsTo
    {
        return $this->belongsTo(VolunteerServiceType::class);
    }
}
