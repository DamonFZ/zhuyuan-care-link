<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 修复补丁：生产环境 users 表缺失后续迁移添加的列
 *
 * 背景：生产库 users 表缺少 points 等列，但 `php artisan migrate` 提示
 * "Nothing to migrate" —— 说明生产的 migrations 表已记录这些迁移执行过
 * （通常是从旧环境导入库时连带导入了 migrations 记录表，但实际表结构
 * 停留在更老的版本）。直接修改旧迁移文件不会生效，因此新增本补丁迁移，
 * 用 Schema::hasColumn 做幂等判断，缺失的列才补上，已存在的列跳过，
 * 对本地（列已齐全）和生产（缺列）都安全。
 *
 * 覆盖的后加列（对齐本地 users 表最终结构）：
 *   points, volunteer_hours, volunteer_level_id, role, avatar, openid, session_key
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'points')) {
                $table->decimal('points', 10, 2)->default(0)->comment('consume points balance');
            }
            if (! Schema::hasColumn('users', 'volunteer_hours')) {
                $table->decimal('volunteer_hours', 10, 2)->default(0)->comment('volunteer service hours');
            }
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('resident')->comment('user role');
            }
            if (! Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->comment('avatar url');
            }
            if (! Schema::hasColumn('users', 'openid')) {
                $table->string('openid')->nullable()->unique();
            }
            if (! Schema::hasColumn('users', 'session_key')) {
                $table->string('session_key')->nullable();
            }
            if (! Schema::hasColumn('users', 'volunteer_level_id')) {
                $table->unsignedBigInteger('volunteer_level_id')->nullable()->after('id');
                // 仅在 volunteer_levels 表存在时加外键，避免生产缺表时迁移失败
                if (Schema::hasTable('volunteer_levels')) {
                    $table->foreign('volunteer_level_id')
                        ->references('id')
                        ->on('volunteer_levels')
                        ->onDelete('set null');
                }
            }
        });
    }

    public function down(): void
    {
        // 修复性补丁，不支持回滚（回滚会删掉业务在用的列，风险高）
    }
};
