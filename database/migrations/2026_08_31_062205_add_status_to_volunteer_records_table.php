<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('volunteer_records', function (Blueprint $table) {
            $table->string('status')
                ->default('completed')
                ->after('reward_points')
                ->comment('状态: completed/revoked');
        });
    }

    public function down(): void
    {
        Schema::table('volunteer_records', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
