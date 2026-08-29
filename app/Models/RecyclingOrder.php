<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecyclingOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        "user_id",
        "weight",
        "reward_type",
        "reward_amount",
        "status",
    ];

    protected $casts = [
        "weight" => "decimal:2",
        "reward_amount" => "decimal:2",
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
