<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 志愿活动报名表：用户可提前报名志愿活动
     */
    public function up(): void
    {
        Schema::create('volunteer_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('报名用户');
            $table->foreignId('volunteer_activity_id')
                ->constrained('volunteer_activities')
                ->onDelete('cascade')
                ->comment('关联志愿活动');
            $table->string('status')
                ->default('registered')
                ->comment('报名状态：registered 已报名 / cancelled 已取消');
            $table->timestamps();

            // 同一用户对同一活动只能有一条有效报名记录
            $table->unique(['user_id', 'volunteer_activity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_registrations');
    }
};
