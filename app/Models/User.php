<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, LogsActivity;

    protected $fillable = [
        "name",
        "email",
        "password",
        "openid",
        "session_key",
        "avatar",
        "points",
        "volunteer_hours",
        "volunteer_level_id",
        "role",
    ];

    protected $hidden = [
        "password",
        "remember_token",
    ];

    protected $casts = [
        "email_verified_at" => "datetime",
        "password" => "hashed",
        "points" => "decimal:2",
        "volunteer_hours" => "decimal:2",
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }

    public function volunteerLevel(): BelongsTo
    {
        return $this->belongsTo(VolunteerLevel::class);
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function recyclingOrders(): HasMany
    {
        return $this->hasMany(RecyclingOrder::class);
    }

    public function volunteerRecords(): HasMany
    {
        return $this->hasMany(VolunteerRecord::class);
    }


    /**
     * 修改用户消费金（积分）余额并写入流水
     * 保证在 DB 事务内，且通过 bcadd/bcsub/行锁保证并发与精度安全
     *
     * @param float|string|int $changeAmount 变动额（正数增加，负数扣除）
     * @param string $eventName 事件描述
     * @return bool 是否成功
     * @throws \Exception
     */
    public function modifyPoints($changeAmount, string $eventName): bool
    {
        // 规范化为两位小数的字符串，避免浮点
        $changeAmount = number_format((float)$changeAmount, 2, '.', '');

        if (bccomp($changeAmount, '0.00', 2) === 0) {
            // 变动为 0 跳过
            return true;
        }

        return DB::transaction(function () use ($changeAmount, $eventName) {
            // 行锁 + 重新查询当前余额，确保并发下不覆盖
            $locked = User::where('id', $this->id)
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                throw new \RuntimeException("用户不存在，无法执行消费金变动");
            }

            $original = number_format((float)$locked->points, 2, '.', '');

            // 判断是否为扣除场景
            if (bccomp($changeAmount, '0.00', 2) < 0) {
                $newVal = bcadd($original, $changeAmount, 2); // 负变相加等于减
                if (bccomp($newVal, '0.00', 2) < 0) {
                    throw new \RuntimeException("用户消费金不足：余额 {$original}，需要扣 " . bcmul($changeAmount, '-1', 2));
                }
            } else {
                $newVal = bcadd($original, $changeAmount, 2);
            }

            // 写入余额（直接更新锁住的行）
            DB::table('users')->where('id', $this->id)->update([
                'points' => $newVal,
                'updated_at' => now(),
            ]);

            // 同步本地对象属性，便于外部继续用
            $this->points = $newVal;

            // 写入流水
            $this->pointTransactions()->create([
                'event_name'      => $eventName,
                'original_points' => $original,
                'change_points'   => $changeAmount,
                'new_points'      => $newVal,
            ]);

            return true;
        });
    }
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}