<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("volunteer_records", function (Blueprint $table) {
            $table->foreignId("volunteer_service_type_id")
                ->nullable()
                ->after("user_id")
                ->constrained("volunteer_service_types")->nullOnDelete();

            $table->decimal("reward_points", 8, 2)
                ->default("0.00")
                ->after("final_hours")
                ->comment("本次服务折算的消费金");
        });
    }

    public function down(): void
    {
        Schema::table("volunteer_records", function (Blueprint $table) {
            $table->dropForeign(["volunteer_service_type_id"]);
            $table->dropColumn(["volunteer_service_type_id", "reward_points"]);
        });
    }
};
