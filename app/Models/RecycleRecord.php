<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * 【机构内部回收员工】上门回收登记记录
 *
 * ⚠️ 业务隔离声明：
 *   本表（recycle_records）仅承载「机构内部回收员工」上门回收时的登记数据：
 *   回收人员(user_id)、衣物图片(clothes_image)、秤图片(scale_image)、实际重量(weight)。
 *   它与面向社区居民的「旧衣回收预约」是两套完全独立的业务，禁止混用：
 *   - 居民回收预约 → RecyclingOrder 模型 → recycling_orders 表（含预约时间、状态、奖励结算等字段）
 *   - 内部回收登记 → 本模型       → recycle_records 表（仅实际回收登记，无预约/奖励逻辑）
 *
 * 重量统计口径：本模型 weight 之和仅代表内部回收业务量，与居民模块完全分离。
 */
class RecycleRecord extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        "user_id",
        "clothes_image",
        "scale_image",
        "weight",
    ];

    protected $casts = [
        "weight" => "decimal:2",
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName("recycle_record");
    }

    /**
     * 提交/回收人员
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 衣物图片完整可访问 URL（给 App 端）
     * 存储走 public 磁盘（storage/app/public），经 public/storage 软链访问。
     */
    public function getClothesImageUrlAttribute(): ?string
    {
        return $this->resolveImageUrl($this->clothes_image);
    }

    /**
     * 秤重量图片完整可访问 URL
     */
    public function getScaleImageUrlAttribute(): ?string
    {
        return $this->resolveImageUrl($this->scale_image);
    }

    private function resolveImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }
        $path = trim($path, "/");
        if (str_starts_with($path, "http")) {
            return $path;
        }
        return url("storage/" . $path);
    }
}
