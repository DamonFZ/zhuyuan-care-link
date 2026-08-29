<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("volunteer_levels", function (Blueprint $table) {
            $table->id();
            $table->string("name")->comment("等级名称");
            $table->decimal("multiplier", 4, 2)->default("1.00")->comment("加成系数");
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("volunteer_levels");
    }
};
