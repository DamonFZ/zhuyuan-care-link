<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("volunteer_records", function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->constrained("users")->onDelete("cascade");
            $table->string("title")->comment("服务项目名称");
            $table->integer("base_hours")->comment("基础时长");
            $table->decimal("multiplier", 3, 1)->default(1.0)->comment("加成系数");
            $table->decimal("final_hours", 8, 1)->comment("最终核算时长");
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("volunteer_records");
    }
};
