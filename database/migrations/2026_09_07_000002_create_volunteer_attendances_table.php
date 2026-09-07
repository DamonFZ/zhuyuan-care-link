<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('志愿者');
            $table->foreignId('volunteer_activity_id')
                ->constrained('volunteer_activities')
                ->onDelete('cascade')
                ->comment('志愿活动');
            $table->dateTime('check_in_time')->comment('签到时间');
            $table->dateTime('check_out_time')->nullable()->comment('签退时间');
            $table->string('status', 20)->default('checked_in')
                ->comment('状态：checked_in 进行中 / completed 已完成');
            $table->timestamps();

            $table->index(['user_id', 'volunteer_activity_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_attendances');
    }
};
