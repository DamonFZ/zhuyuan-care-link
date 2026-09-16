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
        'activity_date',
        'max_hours',
        'status',
        'qrcode_token',
    ];

    protected $casts = [
        'status'        => 'boolean',
        'activity_date' => 'date',
        'max_hours'     => 'decimal:2',
    ];

    /**
     * API 序列化时附带的动态属性
     */
    protected $appends = ['is_ended'];

    /**
     * 动态属性：活动是否已结束（活动日期早于今天）
     */
    public function getIsEndedAttribute(): bool
    {
        if (!$this->activity_date) {
            return false;
        }
        return $this->activity_date->toDateString() < now()->toDateString();
    }

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

    /**
     * 活动的报名记录
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(VolunteerRegistration::class);
    }
}
