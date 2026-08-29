<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("volunteer_service_types", function (Blueprint $table) {
            $table->id();
            $table->string("name")->unique()->comment("岗位名称");
            $table->decimal("base_reward_rate", 4, 2)->default("1.00")->comment("基础消费金时薪(元/小时)");
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("volunteer_service_types");
    }
};
