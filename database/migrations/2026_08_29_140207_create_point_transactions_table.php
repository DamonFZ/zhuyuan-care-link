<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("point_transactions", function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->constrained("users")->onDelete("cascade");
            $table->string("event_name")->comment("变动事件描述");
            $table->decimal("original_points", 10, 2)->comment("变动前积分余额");
            $table->decimal("change_points", 10, 2)->comment("变动额(正增负减)");
            $table->decimal("new_points", 10, 2)->comment("变动后积分余额");
            $table->timestamps();

            $table->index("user_id");
            $table->index("created_at");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("point_transactions");
    }
};
