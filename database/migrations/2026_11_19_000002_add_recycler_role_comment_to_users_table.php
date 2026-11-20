<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * users.role 为 string 自由枚举字段，新增 recycler（回收员）角色值。
     * 本迁移仅更新列注释以文档化新角色（doctrine/dbal 已安装，->change() 可用），
     * 不改变列类型与默认值，向后兼容现有 resident / merchant / operator。
     */
    public function up(): void
    {
        Schema::table("users", function (Blueprint $table) {
            $table->string("role")
                ->default("resident")
                ->comment("角色: resident=社区居民, merchant=核销商户, operator=操作员, recycler=回收员")
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table("users", function (Blueprint $table) {
            $table->string("role")
                ->default("resident")
                ->comment("角色: resident=社区居民, merchant=核销商户, operator=操作员")
                ->change();
        });
    }
};
