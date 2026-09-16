<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 累计志愿时长需支持小数（如 0.98 小时），
     * 将 users.volunteer_hours 从 integer 改为 decimal(10,2)。
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // $table->decimal('volunteer_hours', 10, 2)
            //     ->default(0)
            //     ->comment('累计志愿时长(小时，仅累加base_hours)')
            //     ->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // $table->integer('volunteer_hours')
            //     ->default(0)
            //     ->comment('累计志愿时长')
            //     ->change();
        });
    }
};
