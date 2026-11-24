<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 为内部回收称重记录（recycle_records）补充多图 / 金额 / 备注字段。
     *
     * ⚠️ 兼容历史数据：
     *   - 旧字段 clothes_image / scale_image 保持不变且仍为 nullable，历史记录不受影响；
     *   - 新增字段全部 nullable，旧记录这些字段为空，由访问器在展示层做兼容归一化。
     *
     * 业务隔离：仅操作 recycle_records 表，与 recycling_orders / recycle_bin_inspections 互不干扰。
     */
    public function up(): void
    {
        Schema::table("recycle_records", function (Blueprint $table) {
            if (!Schema::hasColumn("recycle_records", "image_urls")) {
                $table->json("image_urls")
                    ->nullable()
                    ->after("scale_image")
                    ->comment("现场图片路径数组（public 磁盘相对路径，最多 30 张）");
            }
            if (!Schema::hasColumn("recycle_records", "amount")) {
                $table->decimal("amount", 10, 2)
                    ->nullable()
                    ->after("weight")
                    ->comment("金额（元），选填");
            }
            if (!Schema::hasColumn("recycle_records", "remark")) {
                $table->string("remark", 500)
                    ->nullable()
                    ->after("amount")
                    ->comment("备注，选填");
            }
        });
    }

    public function down(): void
    {
        Schema::table("recycle_records", function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn("recycle_records", "image_urls")) {
                $columns[] = "image_urls";
            }
            if (Schema::hasColumn("recycle_records", "amount")) {
                $columns[] = "amount";
            }
            if (Schema::hasColumn("recycle_records", "remark")) {
                $columns[] = "remark";
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
