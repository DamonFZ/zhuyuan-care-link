<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * 【机构内部回收员工】回收箱巡检记录
 *
 * ⚠️ 业务隔离声明：
 *   本表（recycle_bin_inspections）仅承载「回收箱状态巡检」数据：
 *   提交人员(user_id)、箱号(bin_code)、状态(status)、照片(images)、定位(lat/lng)。
 *   与居民回收预约（RecyclingOrder / recycling_orders）、内部回收称重记录
 *   （RecycleRecord / recycle_records）是三套完全独立的业务，禁止混用。
 */
class RecycleBinInspection extends Model
{
    use HasFactory, LogsActivity;

    /** 状态枚举：良好 */
    public const STATUS_GOOD = "good";
    /** 状态枚举：生锈 */
    public const STATUS_RUSTY = "rusty";
    /** 状态枚举：损坏 */
    public const STATUS_DAMAGED = "damaged";

    /** 状态 → 中文文案映射 */
    public const STATUS_MAP = [
        self::STATUS_GOOD    => "良好",
        self::STATUS_RUSTY   => "生锈",
        self::STATUS_DAMAGED => "损坏",
    ];

    protected $fillable = [
        "user_id",
        "bin_code",
        "status",
        "images",
        "latitude",
        "longitude",
    ];

    protected $casts = [
        "images"    => "array",
        "latitude"  => "decimal:7",
        "longitude" => "decimal:7",
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName("recycle_bin_inspection");
    }

    /**
     * 提交巡检的人员
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 状态中文文案（访问器：status_text）
     */
    public function getStatusTextAttribute(): string
    {
        return self::STATUS_MAP[$this->status] ?? $this->status;
    }

    /**
     * 每张巡检照片的完整可访问 URL 数组（访问器：images_urls）
     * 存储走 public 磁盘（storage/app/public），经 public/storage 软链访问。
     */
    public function getImagesUrlsAttribute(): array
    {
        $paths = is_array($this->images) ? $this->images : [];

        return array_values(array_filter(array_map(
            fn ($path) => $this->resolveImageUrl(is_string($path) ? $path : null),
            $paths
        )));
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
