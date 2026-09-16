<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerRegistration extends Model
{
    protected $fillable = [
        'user_id',
        'volunteer_activity_id',
        'status',
    ];

    /**
     * 报名所属用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 报名所属活动
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(VolunteerActivity::class, 'volunteer_activity_id');
    }
}
