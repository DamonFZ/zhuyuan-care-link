<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 扫码签到/签退自动结算需要支持小数时长（如 1.50 小时），
     * 将 base_hours 从 integer 改为 decimal。
     */
    public function up(): void
    {
        Schema::table('volunteer_records', function (Blueprint $table) {
            $table->decimal('base_hours', 8, 2)
                ->comment('基础时长(小时)')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('volunteer_records', function (Blueprint $table) {
            $table->integer('base_hours')
                ->comment('基础时长')
                ->change();
        });
    }
};
