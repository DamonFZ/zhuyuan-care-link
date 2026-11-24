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
 *   回收人员(user_id)、现场图片(image_urls，多图)、实际重量(weight)、金额(amount)、备注(remark)。
 *   它与面向社区居民的「旧衣回收预约」是两套完全独立的业务，禁止混用：
 *   - 居民回收预约 → RecyclingOrder 模型 → recycling_orders 表（含预约时间、状态、奖励结算等字段）
 *   - 内部回收登记 → 本模型       → recycle_records 表（仅实际回收登记，无预约/奖励逻辑）
 *
 * 重量统计口径：本模型 weight 之和仅代表内部回收业务量，与居民模块完全分离。
 *
 * 字段演进兼容：
 *   - 新记录以 image_urls（JSON 数组，相对路径）为主，最多 30 张；
 *   - 历史记录可能只有旧字段 clothes_image / scale_image（单图），由访问器统一归一化合并，
 *     展示侧 / API 只依赖 full_image_urls / display_image_paths，避免规则散落。
 */
class RecycleRecord extends Model
{
    use HasFactory, LogsActivity;

    /** 单条记录最多上传图片张数（与校验规则保持一致） */
    public const MAX_IMAGES = 30;

    protected $fillable = [
        "user_id",
        "clothes_image",
        "scale_image",
        "image_urls",
        "weight",
        "amount",
        "remark",
        // 允许后台（Filament）补录/修正回收日期；API 端不传该字段，仍由 Eloquent 自动写入
        "created_at",
    ];

    protected $casts = [
        "weight"     => "decimal:2",
        "amount"     => "decimal:2",
        "image_urls" => "array",
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
     * 归一化：合并 image_urls 与旧字段 clothes_image / scale_image，
     * 输出去重、去空后的相对路径（或已是完整 URL 的原值）数组。
     *
     * 这是图片路径的单一入口：API 的 full_image_urls 与 Filament 的展示都基于此，
     * 保证“鉴权/展示/URL 生成”对同一文件的解释一致（避免多形态漂移）。
     *
     * @return string[]
     */
    public function getDisplayImagePathsAttribute(): array
    {
        $paths = is_array($this->image_urls) ? $this->image_urls : [];

        // 兼容历史单图字段：仅当新数组为空时才补入，避免与新数据重复
        $legacy = array_values(array_filter(
            [$this->clothes_image, $this->scale_image],
            fn ($p) => is_string($p) && $p !== ""
        ));
        if (empty($paths) && !empty($legacy)) {
            $paths = $legacy;
        }

        // 去重 + 仅保留字符串
        $seen = [];
        $result = [];
        foreach ($paths as $p) {
            if (!is_string($p) || $p === "") {
                continue;
            }
            if (isset($seen[$p])) {
                continue;
            }
            $seen[$p] = true;
            $result[] = $p;
        }

        return $result;
    }

    /**
     * 全部现场图片的完整可访问 URL 数组（给 App 端 / 后台详情）
     * 基于 display_image_paths 归一化结果生成，兼容相对路径与完整 URL。
     *
     * @return string[]
     */
    public function getFullImageUrlsAttribute(): array
    {
        return array_values(array_filter(array_map(
            fn (?string $path) => $this->resolveImageUrl($path),
            $this->display_image_paths
        )));
    }

    /**
     * 衣物图片完整可访问 URL（兼容旧字段，给历史 App 端）
     * 存储走 public 磁盘（storage/app/public），经 public/storage 软链访问。
     */
    public function getClothesImageUrlAttribute(): ?string
    {
        return $this->resolveImageUrl($this->clothes_image);
    }

    /**
     * 秤重量图片完整可访问 URL（兼容旧字段）
     */
    public function getScaleImageUrlAttribute(): ?string
    {
        return $this->resolveImageUrl($this->scale_image);
    }

    /**
     * 单条路径 → 完整可访问 URL：已是 http(s) 则原样返回，否则按 public 磁盘拼接。
     */
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
