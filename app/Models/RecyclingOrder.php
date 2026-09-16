<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class RecyclingOrder extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        "user_id",
        "estimated_weight",
        "appointment_time",
        "images",
        "remark",
        "actual_weight",
        "weight",
        "reward_type",
        "reward_amount",
        "status",
    ];

    protected $casts = [
        "images"          => "array",
        "appointment_time" => "datetime",
        "actual_weight"   => "decimal:2",
        "weight"          => "decimal:2",
        "reward_amount"   => "decimal:2",
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
}
