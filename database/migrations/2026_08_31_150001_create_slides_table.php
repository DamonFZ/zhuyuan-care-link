<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slides', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable()->comment('轮播图标题');
            $table->string('image_path')->comment('轮播图存储路径');
            $table->integer('sort_order')->default(0)->comment('展示排序: 越小越前');
            $table->boolean('is_visible')->default(true)->comment('是否前台可见');
            $table->timestamps();
            $table->index(['is_visible', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slides');
    }
};
