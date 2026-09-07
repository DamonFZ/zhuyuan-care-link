<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VolunteerActivity extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'title',
        'volunteer_service_type_id',
        'status',
        'qrcode_token',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }

    /**
     * 创建活动时自动生成 32 位唯一二维码 Token
     */
    protected static function booted(): void
    {
        static::creating(function (VolunteerActivity $activity) {
            if (empty($activity->qrcode_token)) {
                $activity->qrcode_token = Str::random(32);
            }
        });
    }

    public function volunteerServiceType(): BelongsTo
    {
        return $this->belongsTo(VolunteerServiceType::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(VolunteerAttendance::class);
    }
}
