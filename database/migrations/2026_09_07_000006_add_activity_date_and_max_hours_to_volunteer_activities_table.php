<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 给志愿活动增加活动日期与单场最大工时，
     * 用于扫码打卡的跨日失效校验与工时熔断防刷。
     */
    public function up(): void
    {
        Schema::table('volunteer_activities', function (Blueprint $table) {
            $table->date('activity_date')
                ->nullable()
                ->after('volunteer_service_type_id')
                ->comment('活动日期（跨日扫码无效）');
            $table->decimal('max_hours', 8, 2)
                ->default(4.00)
                ->after('activity_date')
                ->comment('单场最大生效工时（防挂机熔断上限）');
        });

        // 历史活动回填：活动日期取创建日期，避免老数据扫码被拦截
        \Illuminate\Support\Facades\DB::table('volunteer_activities')
            ->whereNull('activity_date')
            ->update(['activity_date' => \Illuminate\Support\Facades\DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('volunteer_activities', function (Blueprint $table) {
            $table->dropColumn(['activity_date', 'max_hours']);
        });
    }
};
