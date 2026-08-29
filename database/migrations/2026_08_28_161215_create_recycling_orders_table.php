<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("recycling_orders", function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->constrained("users")->onDelete("cascade");
            $table->decimal("weight", 8, 2)->comment("回收重量/斤");
            $table->string("reward_type")->default("points")->comment("积分或现金");
            $table->decimal("reward_amount", 8, 2)->comment("折算金额");
            $table->string("status")->default("completed");
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("recycling_orders");
    }
};
