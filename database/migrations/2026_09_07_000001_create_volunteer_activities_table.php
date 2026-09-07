<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_activities', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('活动名称');
            $table->foreignId('volunteer_service_type_id')
                ->constrained('volunteer_service_types')
                ->onDelete('restrict')
                ->comment('关联岗位类型');
            $table->boolean('status')->default(true)->comment('是否启用');
            $table->string('qrcode_token', 64)->unique()->comment('现场二维码唯一标识');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_activities');
    }
};
