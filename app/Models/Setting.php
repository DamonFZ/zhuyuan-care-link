<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        "key",
        "name",
        "value",
    ];

    /**
     * 快捷获取某配置值
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $row = static::where("key", $key)->first();
        return $row ? $row->value : $default;
    }
}
