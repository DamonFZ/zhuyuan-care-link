<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 给打卡记录表增加 remark 字段，用于管理员补签备注
     */
    public function up(): void
    {
        Schema::table('volunteer_attendances', function (Blueprint $table) {
            $table->string('remark')->nullable()->after('status')
                ->comment('管理员补签备注');
        });
    }

    public function down(): void
    {
        Schema::table('volunteer_attendances', function (Blueprint $table) {
            $table->dropColumn('remark');
        });
    }
};
