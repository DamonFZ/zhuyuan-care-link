<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 回收箱巡检记录（回收业务 App：司机/回收员巡检回收箱状态上报）
     *
     * 业务隔离：本表（recycle_bin_inspections）仅承载回收箱状态巡检数据，
     * 与居民回收预约（recycling_orders）、内部回收称重记录（recycle_records）
     * 完全独立，互不干扰。
     */
    public function up(): void
    {
        Schema::create("recycle_bin_inspections", function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")
                ->constrained("users")
                ->onDelete("cascade")
                ->comment("提交巡检的人员");
            $table->string("bin_code", 50)->nullable()->comment("回收箱编号，如 ZS0001");
            $table->string("status", 20)->default("good")->comment("状态：good=良好 rusty=生锈 damaged=损坏");
            $table->json("images")->comment("巡检照片相对路径数组（public 磁盘）");
            $table->decimal("latitude", 10, 7)->nullable()->comment("纬度，定位失败为 null");
            $table->decimal("longitude", 10, 7)->nullable()->comment("经度，定位失败为 null");
            $table->timestamps();

            // 列表按时间倒序 / 个人回看
            $table->index(["user_id", "created_at"]);
            // 按箱号、状态筛选
            $table->index("bin_code");
            $table->index("status");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("recycle_bin_inspections");
    }
};
