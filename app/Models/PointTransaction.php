<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        "user_id",
        "event_name",
        "original_points",
        "change_points",
        "new_points",
    ];

    protected $casts = [
        "original_points" => "decimal:2",
        "change_points"   => "decimal:2",
        "new_points"      => "decimal:2",
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
