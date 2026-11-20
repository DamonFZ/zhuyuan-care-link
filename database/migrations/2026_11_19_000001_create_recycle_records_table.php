<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 回收业务 App：回收记录（回收员上门实际称重记录）
     * 与 residents 端的 recycling_orders（预约单）相互独立，
     * 本表记录回收员提交的衣物照片 + 秤重照片 + 实际重量。
     */
    public function up(): void
    {
        Schema::create("recycle_records", function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")
                ->constrained("users")
                ->onDelete("cascade")
                ->comment("提交/回收人员");
            $table->string("clothes_image")->nullable()->comment("衣物图片存储路径（public 磁盘）");
            $table->string("scale_image")->nullable()->comment("秤重量图片存储路径（public 磁盘）");
            $table->decimal("weight", 8, 2)->default(0)->comment("实际回收重量，单位 kg");
            $table->timestamps();

            // 历史记录按时间倒序展示 / 日期统计
            $table->index(["user_id", "created_at"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("recycle_records");
    }
};
