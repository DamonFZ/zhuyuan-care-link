<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Slide extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'title',
        'image_path',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_visible' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('slide');
    }

    /**
     * 轮播图完整可访问 URL（给小程序端）
     */
    public function getImageUrlAttribute(): string
    {
        $path = trim($this->image_path, '/');
        // FileUpload directory('slides') 实际存 storage/app/public/slides/xxx，通过 public/storage/ 软链访问
        if (str_starts_with($path, 'http')) return $this->image_path;
        return url('storage/' . $path);
    }
}
