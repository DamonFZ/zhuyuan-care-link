<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 为 recycling_orders 追加上门回收预约相关字段
     *  - estimated_weight：预估重量段（如 3~20kg）
     *  - appointment_time：预约上门时间
     *  - images：用户上传的图片数组（JSON）
     *  - remark：备注
     *  - actual_weight：实际称重（nullable）
     *  - status 默认值从 completed 改为 pending
     */
    public function up(): void
    {
        Schema::table('recycling_orders', function (Blueprint $table) {
            $table->string('estimated_weight')
                ->nullable()
                ->after('user_id')
                ->comment('预估重量段，如 3~20kg');
            $table->dateTime('appointment_time')
                ->nullable()
                ->after('estimated_weight')
                ->comment('预约上门时间');
            $table->json('images')
                ->nullable()
                ->after('appointment_time')
                ->comment('用户上传图片数组');
            $table->string('remark')
                ->nullable()
                ->after('images')
                ->comment('备注');
            $table->decimal('actual_weight', 8, 2)
                ->nullable()
                ->after('remark')
                ->comment('实际称重');
        });

        // status 默认值改为 pending（新增预约默认为待处理）
        // 注：不使用 ->change() 以避免依赖 doctrine/dbal
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE recycling_orders MODIFY COLUMN status varchar(255) NOT NULL DEFAULT 'pending' COMMENT 'pending/processing/completed/cancelled/revoked'"
        );
    }

    public function down(): void
    {
        Schema::table('recycling_orders', function (Blueprint $table) {
            $table->dropColumn([
                'estimated_weight',
                'appointment_time',
                'images',
                'remark',
                'actual_weight',
            ]);
        });

        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE recycling_orders MODIFY COLUMN status varchar(255) NOT NULL DEFAULT 'completed'"
        );
    }
};
