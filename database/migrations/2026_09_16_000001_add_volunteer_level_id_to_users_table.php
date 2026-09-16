<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 修复：历史迁移 2026_08_29_142541 的 up()/down() 为空，
     * 导致 users.volunteer_level_id 列从未被正式创建。
     * 生产环境执行迁移后该列缺失，DashboardStats 等查询报错。
     * 本迁移幂等补建该列及其外键约束。
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'volunteer_level_id')) {
                $table->unsignedBigInteger('volunteer_level_id')
                    ->nullable()
                    ->after('role')
                    ->comment('志愿等级ID');

                $table->foreign('volunteer_level_id')
                    ->references('id')
                    ->on('volunteer_levels')
                    ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'volunteer_level_id')) {
                try {
                    $table->dropForeign(['volunteer_level_id']);
                } catch (\Throwable $e) {
                    // 外键不存在则忽略
                }
                $table->dropColumn('volunteer_level_id');
            }
        });
    }
};
