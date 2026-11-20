<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

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
