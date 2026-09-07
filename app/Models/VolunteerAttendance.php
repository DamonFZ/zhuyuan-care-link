<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VolunteerAttendance extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id',
        'volunteer_activity_id',
        'check_in_time',
        'check_out_time',
        'status',
    ];

    protected $casts = [
        'check_in_time'  => 'datetime',
        'check_out_time' => 'datetime',
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

    public function volunteerActivity(): BelongsTo
    {
        return $this->belongsTo(VolunteerActivity::class);
    }
}
